"""Automated security checks for Campus Notes (local lab only).
Usage: python3 tests/run_tests.py http://127.0.0.1:8001 path/to/test.sqlite
Uses only synthetic accounts. Prints PASS/FAIL per check; never prints secrets."""
import http.cookiejar, json, re, sqlite3, sys, urllib.error, urllib.parse, urllib.request

BASE, DBFILE = sys.argv[1], sys.argv[2]
ALEX = ("alex@example.test", "LabOnly-Alex-123")
SAM = ("sam@example.test", "LabOnly-Sam-456")
results = []

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None

class Client:
    def __init__(self, base=None):
        self.base = base or BASE
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect)
    def req(self, path, data=None, headers=None, method=None, raw=None):
        body = raw if raw is not None else (urllib.parse.urlencode(data).encode() if data is not None else None)
        r = urllib.request.Request(self.base + path, data=body, headers=headers or {}, method=method)
        try:
            resp = self.op.open(r); return resp.status, resp.headers, resp.read().decode()
        except urllib.error.HTTPError as e:
            return e.code, e.headers, e.read().decode()
    def csrf(self):
        _, _, html = self.req("/")
        m = re.search(r'name="csrf" value="([0-9a-f]+)"', html); return m.group(1) if m else ""
    def sid(self):
        return next((c.value for c in self.jar if c.name == "CNSESSID"), None)
    def login(self, creds):
        return self.req("/", {"csrf": self.csrf(), "action": "login", "email": creds[0], "password": creds[1]})
    def post(self, data):
        data = dict(data, csrf=self.csrf()); return self.req("/", data)
    def api_token(self):
        s, _, b = self.req("/api.php"); return json.loads(b)["csrf"] if s == 200 else ""

def db(q, *a):
    c = sqlite3.connect(DBFILE); r = c.execute(q, a).fetchall(); c.close(); return r

def check(num, issue, name, ok):
    results.append((num, issue, name, ok)); print(f"{'PASS' if ok else 'FAIL'}  T{num:02d} [issue {issue}] {name}")

# --- Login / session ---
a = Client()
s, _, html = a.login((ALEX[0], "wrong-password"))
check(1, "6", "Invalid login shows generic message and no notes", "Invalid email or password." in html and "My notes" not in html)
s, _, html = a.login(("' OR '1'='1' -- ", "x"))
check(2, "1", "SQL injection in login email does not sign in", "My notes" not in html)
before = a.sid(); s, h, _ = a.login(ALEX); after = a.sid()
check(3, "2/6", "Valid login works (bcrypt) and session ID changes", s == 302 and before and after and before != after)
_, _, html = a.req("/")
check(4, "3", "Alex sees own notes only, not Sam's", "Alex: course reminder" in html and "Sam: study plan" not in html)

b = Client(); b.login(SAM)

# --- Authorization on notes (web) ---
_, _, html = a.req("/?edit=3")
check(5, "3", "Alex cannot open Sam's note in edit form", "Sam: study plan" not in html and "Note not found." in html)
s, _, _ = a.post({"action": "update", "id": "3", "title": "hacked", "body": "hacked"})
check(6, "3", "Alex cannot update Sam's note (404, unchanged)", s == 404 and db("SELECT title FROM notes WHERE id=3")[0][0] == "Sam: study plan")
s, _, _ = a.post({"action": "delete", "id": "4"})
check(7, "3", "Alex cannot delete Sam's note (404, still exists)", s == 404 and db("SELECT COUNT(*) FROM notes WHERE id=4")[0][0] == 1)

# --- CSRF / unsafe methods ---
a.req("/?delete=1")
check(8, "5", "Old GET ?delete=1 link does nothing", db("SELECT COUNT(*) FROM notes WHERE id=1")[0][0] == 1)
s, _, _ = a.req("/", {"action": "delete", "id": "1"})
check(9, "5", "POST delete without CSRF token is rejected (403)", s == 403 and db("SELECT COUNT(*) FROM notes WHERE id=1")[0][0] == 1)
s, _, _ = a.req("/", {"action": "delete", "id": "1", "csrf": a.csrf()}, headers={"Origin": "http://evil.example"})
check(10, "5", "POST with valid token but foreign Origin is rejected", s == 403)
s, _, _ = a.req("/", {"action": "delete", "id": "1", "csrf": b.csrf()})
check(11, "5", "Sam's CSRF token does not work in Alex's session", s == 403)

# --- Owner / role from client ---
a.post({"action": "save", "user_id": "2", "title": "owner test", "body": "x"})
check(12, "7", "Hidden user_id=2 ignored; note owned by Alex", db("SELECT user_id FROM notes WHERE title='owner test'") == [(1,)])
c = Client()
c.req("/", {"csrf": c.csrf(), "action": "register", "email": "eve@example.test", "password": "LabOnly-Eve-7890", "role": "admin"})
row = db("SELECT role, password_hash FROM users WHERE email='eve@example.test'")
check(13, "7", "Register with role=admin creates a student", row and row[0][0] == "student")
check(14, "2", "New password stored with bcrypt, not MD5", row and row[0][1].startswith("$2y$") and len(row[0][1]) == 60)

# --- XSS ---
xss = '<script>alert(1)</script><img src=x onerror=alert(2)>'
a.post({"action": "save", "title": xss, "body": xss})
_, h, html = a.req("/")
check(15, "4", "HTML in note shows as text (escaped)", "<script>alert(1)" not in html and "&lt;script&gt;alert(1)&lt;/script&gt;" in html)
xid = db("SELECT id FROM notes WHERE title=?", xss)[0][0]
_, _, html = a.req(f"/?edit={xid}")
check(16, "4", "HTML in edit form value/textarea is escaped", '"><' not in html.split('name="title" value="')[1][:60] and "&lt;script&gt;" in html)
_, _, html = a.req('/?message=<script>x</script>')
check(17, "4", "?message= parameter is no longer reflected", "<script>x</script>" not in html)
check(18, "4", "CSP header is sent", "default-src 'self'" in (h.get("Content-Security-Policy") or ""))

# --- API ---
n = Client()
s, _, b1 = n.req("/api.php?user_id=1")
check(19, "9", "API GET without session -> 401, no notes", s == 401 and "Alex" not in b1)
s, _, _ = n.req("/api.php", raw=b'{"id":1,"title":"x","body":"x"}', headers={"Content-Type": "application/json"})
check(20, "9", "API POST without session -> 401", s == 401 and db("SELECT title FROM notes WHERE id=1")[0][0] == "Alex: course reminder")
s, _, body = a.req("/api.php?user_id=2")
check(21, "9", "API GET other user's id -> 403", s == 403 and "Sam" not in body)
s, _, body = a.req("/api.php")
ids = {x["user_id"] for x in json.loads(body)["notes"]}
check(22, "9", "API GET returns only own notes", s == 200 and ids == {1})
tok = a.api_token(); jh = {"Content-Type": "application/json", "X-CSRF-Token": tok}
s, _, _ = a.req("/api.php", raw=b'{"id":3,"title":"hacked","body":"hacked"}', headers=jh)
check(23, "9", "API update of Sam's note -> 404, unchanged", s == 404 and db("SELECT title FROM notes WHERE id=3")[0][0] == "Sam: study plan")
s, _, body = a.req("/api.php", raw=b'{"id":1,"title":', headers=jh)
check(24, "10", "Malformed JSON -> 400 with safe message", s == 400 and "Malformed JSON" in body and "Stack" not in body)
s, _, _ = a.req("/api.php", raw=b'[1,2,3]', headers=jh)
check(25, "10", "JSON array instead of object -> 400", s == 400)
s, _, _ = a.req("/api.php", raw=b'{"id":"1 OR 1=1","title":"x","body":"x"}', headers=jh)
check(26, "10", "Bad id type -> 422", s == 422)
s, _, _ = a.req("/api.php", raw=json.dumps({"id": 1, "title": "x" * 500, "body": "x"}).encode(), headers=jh)
check(27, "10", "Too-long title -> 422", s == 422)
s, _, _ = a.req("/api.php", raw=b'{"id":1,"title":"x","body":"x"}', headers={"Content-Type": "text/plain", "X-CSRF-Token": tok})
check(28, "10/11", "Wrong Content-Type (text/plain) -> 415", s == 415)
s, _, _ = a.req("/api.php", raw=b'{"id":1,"title":"x","body":"x"}', headers={"Content-Type": "application/json"})
check(29, "11", "API POST without CSRF header -> 403", s == 403)
s, h, _ = a.req("/api.php", raw=b'{"id":1,"title":"x","body":"x"}', headers=dict(jh, Origin="http://evil.example"))
check(30, "11", "API POST from untrusted Origin -> 403, no CORS allow header", s == 403 and h.get("Access-Control-Allow-Origin") is None)
s, h, _ = a.req("/api.php", headers={"Origin": "http://evil.example"})
check(31, "11", "API GET from untrusted Origin -> 403, no CORS headers", s == 403 and h.get("Access-Control-Allow-Credentials") is None)
s, _, _ = a.req("/api.php", raw=b'{"id":1,"title":"API edit","body":"ok"}', headers=jh)
check(32, "9", "Valid API update of own note -> 200", s == 200 and db("SELECT title FROM notes WHERE id=1")[0][0] == "API edit")
s, _, _ = a.req("/api.php", method="DELETE")
check(33, "9", "Unsupported method -> 405", s == 405)

# --- Web CRUD still works ---
a.post({"action": "update", "id": "2", "title": "Alex: updated", "body": "new body"})
ok_upd = db("SELECT title FROM notes WHERE id=2")[0][0] == "Alex: updated"
s, _, _ = a.post({"action": "delete", "id": "2"})
check(34, "-", "Own note update and delete still work", ok_upd and s == 302 and db("SELECT COUNT(*) FROM notes WHERE id=2")[0][0] == 0)

# --- Logout ---
old = a.sid(); tok = a.csrf()
a.req("/", {"csrf": tok, "action": "logout"})
replay = Client(); replay.jar.set_cookie(http.cookiejar.Cookie(0, "CNSESSID", old, None, False, "127.0.0.1", False, False, "/", True, False, None, False, None, None, {}))
s1, _, _ = replay.req("/api.php")
_, _, html = replay.req("/")
check(35, "6", "After logout the old session cookie gives no access", s1 == 401 and "My notes" not in html)
d = Client(); d.login(ALEX); d.req("/?logout=1"); _, _, html = d.req("/")
check(36, "5", "GET ?logout=1 no longer logs out (state change needs POST)", "My notes" in html)

# --- Files not public ---
s1, _, _ = n.req("/schema.sql"); s2, _, _ = n.req("/src/db.php"); s3, _, _ = n.req("/tests/run_tests.py")
check(37, "8", "schema.sql, src/, tests/ not served (404)", s1 == s2 == s3 == 404)

# --- Error handling: server started with no DB credentials ---
if len(sys.argv) > 3:
    e = Client(sys.argv[3])
    s, _, body = e.req("/", {"csrf": e.csrf(), "action": "login", "email": ALEX[0], "password": ALEX[1]})
    check(38, "8", "DB failure -> 500 generic message, no details", s == 500 and "Something went wrong" in body and "PDO" not in body and "LAB_DB" not in body and ".php" not in body)

failed = [r for r in results if not r[3]]
print(f"\n{len(results) - len(failed)}/{len(results)} passed")
sys.exit(1 if failed else 0)
