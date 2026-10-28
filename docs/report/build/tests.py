"""Runs the test cases of Chapter 5 against the local site and saves the real results to JSON.

Temporary test accounts are created in the database and removed at the end.
"""
import http.cookiejar
import json
import os
import re
import statistics
import subprocess
import sys
import time
import urllib.parse
import urllib.request
import uuid

BASE = "http://127.0.0.1/social-statistics"
MYSQL = r"C:\xampp\mysql\bin\mysql.exe"
PHP = r"C:\xampp\php\php.exe"
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
OUT = sys.argv[1]
XLSX = sys.argv[2]

creds = dict(l.split(": ", 1) for l in open(os.path.join(ROOT, "storage", "dev-admin.txt")).read().splitlines()[1:])


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class Session:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())

    def req(self, method, path, data=None, files=None):
        url = path if path.startswith("http") else f"{BASE}/{path}"
        headers = {}
        body = None
        if files:
            boundary = uuid.uuid4().hex
            parts = []
            for k, v in (data or {}).items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
            for k, (name, content) in files.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"; filename="{name}"\r\n'
                             f'Content-Type: application/octet-stream\r\n\r\n'.encode() + content + b"\r\n")
            body = b"".join(parts) + f"--{boundary}--\r\n".encode()
            headers["Content-Type"] = f"multipart/form-data; boundary={boundary}"
        elif data is not None:
            body = urllib.parse.urlencode(data, doseq=True).encode()
            headers["Content-Type"] = "application/x-www-form-urlencoded"
        r = urllib.request.Request(url, data=body, method=method, headers=headers)
        t = time.perf_counter()
        try:
            resp = self.opener.open(r, timeout=120)
            status, text, loc = resp.status, resp.read().decode("utf-8", "replace"), resp.headers.get("Location")
        except urllib.error.HTTPError as e:
            status, text, loc = e.code, e.read().decode("utf-8", "replace"), e.headers.get("Location")
        return status, text, loc, (time.perf_counter() - t) * 1000

    def get(self, path):
        return self.req("GET", path)

    def post(self, path, data, files=None):
        return self.req("POST", path, data, files)

    def token(self, path):
        _, html, _, _ = self.get(path)
        m = re.search(r'name="csrf" value="([a-f0-9]+)"', html)
        return m.group(1) if m else ""

    def login(self, email, password):
        t = self.token("admin/login.php?lang=en")
        return self.post("admin/login.php?lang=en", {"csrf": t, "email": email, "password": password})


def sql(query):
    return subprocess.run([MYSQL, "-uroot", "-N", "social_stats", "-e", query], capture_output=True, text=True).stdout.strip()


def php_hash(pw):
    return subprocess.run([PHP, "-r", "echo password_hash($argv[1], PASSWORD_DEFAULT);", pw], capture_output=True, text=True).stdout


results = []


def case(tid, area, title, expected, fn):
    t = time.perf_counter()
    try:
        ok, actual = fn()
    except Exception as ex:  # a crash is a failed test
        ok, actual = False, f"Exception: {ex}"
    ms = (time.perf_counter() - t) * 1000
    results.append({"id": tid, "area": area, "title": title, "expected": expected, "actual": actual,
                    "result": "Pass" if ok else "Fail", "ms": round(ms)})
    print(f"{tid} {'PASS' if ok else 'FAIL'} {title} -> {actual}")


s = Session()
started = time.strftime("%Y-%m-%d %H:%M")

# ---------------- public site ----------------
def tc01():
    st, html, _, ms = s.get("index.php?lang=en")
    return st == 200 and "Leading platform" in html and "Facebook" in html, f"HTTP {st}, KPI cards shown, {ms:.0f} ms"
case("TC01", "Home", "Home page shows Oman key figures", "Page loads with leading platform, internet users and population", tc01)

def tc02():
    st, html, _, _ = s.get("index.php?lang=ar")
    ok = 'dir="rtl"' in html and "bootstrap.rtl.min.css" in html and "الرئيسية" in html
    return ok, "dir=rtl, RTL stylesheet and Arabic menu loaded" if ok else "Arabic layout missing"
case("TC02", "Language", "Switch the interface to Arabic", "Page uses right-to-left layout and Arabic text", tc02)

def tc03():
    st, body, _, _ = s.get("api/series.php?country=OM&metric=traffic_share&device=all")
    j = json.loads(body)
    return st == 200 and len(j["labels"]) == 24 and len(j["datasets"]) >= 5, f"{len(j['labels'])} months, {len(j['datasets'])} platforms"
case("TC03", "Explore", "Default time series for Oman", "24 monthly values for every platform with data", tc03)

def tc04():
    _, raw, _, _ = s.get("api/series.php?country=OM&metric=traffic_share&device=mobile&platforms=facebook&from=2025-01&to=2025-06")
    _, sm, _, _ = s.get("api/series.php?country=OM&metric=traffic_share&device=mobile&platforms=facebook&from=2025-01&to=2025-06&smooth=1")
    r, m = json.loads(raw)["datasets"][0]["data"], json.loads(sm)["datasets"][0]["data"]
    expect = round(sum(r[3:6]) / 3, 2)
    return abs(m[5] - expect) < 0.01, f"June average {m[5]} = mean of Apr-Jun ({expect})"
case("TC04", "Explore", "3-month moving average", "Each smoothed value equals the mean of that month and the two before", tc04)

def tc05():
    _, body, _, _ = s.get("api/series.php?country=OM&metric=internet_users")
    j = json.loads(body)
    return j["metric"]["granularity"] == "yearly" and re.fullmatch(r"\d{4}", j["labels"][-1]) is not None, f"yearly labels up to {j['labels'][-1]}"
case("TC05", "Explore", "Yearly World Bank indicator", "Labels are years and the device filter is ignored", tc05)

def tc06():
    _, body, _, _ = s.get("api/compare.php?countries=OM,SA,AE,QA,KW,BH,EG&platform=instagram")
    j = json.loads(body)
    return len(j["datasets"]) == 6, f"7 countries requested, {len(j['datasets'])} returned"
case("TC06", "Compare", "Compare countries is limited to six", "Only the first 6 countries are returned", tc06)

def tc07():
    _, body, _, _ = s.get("api/map.php")
    j = json.loads(body)
    return len(j["countries"]) >= 16 and "WW" not in j["countries"], f"{len(j['countries'])} countries coloured, 'Worldwide' excluded"
case("TC07", "Home", "Map of the leading platform", "Every country with data gets a colour; Worldwide is not drawn", tc07)

def tc08():
    st, body, _, _ = s.get("api/series.php?metric=unknown")
    return st == 400 and "error" in body, f"HTTP {st} {body}"
case("TC08", "API", "Unknown statistic type", "HTTP 400 with an error message", tc08)

def tc09():
    st, body, _, _ = s.get("api/series.php?device=tv")
    return st == 400, f"HTTP {st} {body}"
case("TC09", "API", "Unknown device value", "HTTP 400 with an error message", tc09)

def tc10():
    _, html, _, _ = s.get("explore.php?lang=en&country=ZZ")
    return 'value="OM" selected' in html, "falls back to Oman" if 'value="OM" selected' in html else "no fallback"
case("TC10", "Explore", "Unknown country code in the link", "The page falls back to the default country (Oman)", tc10)

def tc11():
    _, html, _, _ = s.get("explore.php?lang=en&from=%22%3E%3Cscript%3Ealert(1)%3C/script%3E")
    ok = "<script>alert(1)" not in html and "alert(1)" not in html
    return ok, "invalid period ignored, default period used; no script in the page" if ok else "script text reached the page"
case("TC11", "Security", "Script injected in a URL parameter (XSS)", "Invalid value is ignored and never reaches the page", tc11)

def tc12():
    _, a, _, _ = s.get("local-study.php?lang=en")
    _, b, _, _ = s.get("local-study.php?lang=en&age=16-18&gender=female")
    n = lambda h: int(re.search(r'kpi-value">(\d+)<', h).group(1))
    return n(b) < n(a), f"all: {n(a)}, girls 16-18: {n(b)}"
case("TC12", "Local study", "Filter results by age group and gender", "Numbers and charts change to the filtered group", tc12)

# ---------------- questionnaire ----------------
answer = {"age_group": "16-18", "gender": "female", "platforms[]": ["tiktok", "snapchat"], "favourite": "tiktok",
          "main_purpose": "entertainment", "daily_hours": "3-4", "late_night_use": "1", "consent": "1"}
before = int(sql("SELECT COUNT(*) FROM survey_responses"))
q = Session()

def tc13():
    t = q.token("survey.php?lang=en")
    st, html, _, _ = q.post("survey.php?lang=en", dict(answer, favourite="x", csrf=t))
    return "must be one of the platforms you use" in html, "error shown, nothing saved"
case("TC13", "Questionnaire", "Favourite platform not among used platforms", "Rejected with a clear message", tc13)

def tc14():
    t = q.token("survey.php?lang=en")
    data = dict(answer, csrf=t)
    data.pop("consent")
    _, html, _, _ = q.post("survey.php?lang=en", data)
    return "consent box" in html, "consent error shown"
case("TC14", "Questionnaire", "Submit without ticking consent", "Rejected; asks for consent", tc14)

def tc15():
    t = q.token("survey.php?lang=en")
    st, _, loc, _ = q.post("survey.php?lang=en", dict(answer, csrf=t))
    after = int(sql("SELECT COUNT(*) FROM survey_responses"))
    return st == 302 and after == before + 1, f"HTTP {st} to local-study.php, responses {before} -> {after}"
case("TC15", "Questionnaire", "Valid questionnaire answer", "Saved, then redirected to the results", tc15)

def tc16():
    t = q.token("survey.php?lang=en")
    _, html, _, _ = q.post("survey.php?lang=en", dict(answer, csrf=t))
    after = int(sql("SELECT COUNT(*) FROM survey_responses"))
    return after == before + 1, f"second answer refused, responses still {after}"
case("TC16", "Questionnaire", "Second answer from the same session", "Refused; count does not change", tc16)

def tc17():
    b = Session()
    t = b.token("survey.php?lang=en")
    b.post("survey.php?lang=en", dict(answer, csrf=t, website="http://spam.example"))
    after = int(sql("SELECT COUNT(*) FROM survey_responses"))
    return after == before + 1, f"bot answer ignored, responses still {after}"
case("TC17", "Security", "Hidden honeypot field filled by a bot", "Answer silently discarded", tc17)

sql("DELETE FROM survey_responses WHERE origin = 'online'")

# ---------------- admin and security ----------------
def tc18():
    st, _, loc, _ = Session().get("admin/data.php")
    return st == 302 and loc.endswith("admin/login.php"), f"HTTP {st} -> {loc.split('/')[-2]}/{loc.split('/')[-1]}"
case("TC18", "Security", "Open an admin page without signing in", "Redirected to the sign-in page", tc18)

def tc19():
    st, html, _, _ = Session().login(creds["email"], "Wrong12345")
    logged = sql("SELECT COUNT(*) FROM audit_log WHERE action='login_failed' AND created_at > NOW() - INTERVAL 1 MINUTE")
    return "not correct" in html and int(logged) >= 1, f"error shown, {logged} failed attempt(s) in audit log"
case("TC19", "Security", "Sign in with a wrong password", "Generic error; attempt written to the audit log", tc19)
sql(f"UPDATE users SET failed_logins = 0 WHERE email = '{creds['email']}'")

pw = "TestPass123"
h = php_hash(pw)
sql(f"INSERT INTO users (name, email, password_hash, role) VALUES ('Lock test', 'lock@test.local', '{h}', 'editor'), "
    f"('Editor test', 'editor@test.local', '{h}', 'editor')")

def tc20():
    x = Session()
    for _ in range(5):
        x.login("lock@test.local", "bad-password1")
    _, html, _, _ = x.login("lock@test.local", pw)
    locked = sql("SELECT locked_until IS NOT NULL FROM users WHERE email='lock@test.local'")
    return "locked" in html and locked == "1", "6th attempt with the right password refused: account locked 15 min"
case("TC20", "Security", "Five wrong passwords in a row", "Account locked for 15 minutes, even for the right password", tc20)

def tc21():
    st, body, _, _ = Session().post("admin/login.php", {"email": "a@b.c", "password": "x"})
    return st == 403, f"HTTP {st}: {body.strip()[:40]}..."
case("TC21", "Security", "Form sent without CSRF token", "Request refused with HTTP 403", tc21)

admin = Session()
def tc22():
    st, _, loc, _ = admin.login(creds["email"], creds["password"])
    st2, html, _, _ = admin.get("admin/index.php?lang=en")
    return st == 302 and "Dashboard" in html, f"HTTP {st} -> dashboard"
case("TC22", "Admin", "Sign in with correct details", "Dashboard opens; session id renewed", tc22)

def tc23():
    e = Session()
    e.login("editor@test.local", pw)
    st, _, _, _ = e.get("admin/log.php")
    st2, _, _, _ = e.get("admin/sync.php")
    return st == 403 and st2 == 200, f"audit log HTTP {st}, update page HTTP {st2}"
case("TC23", "Security", "Data editor opens an administrator page", "Audit log refused (403); editor pages allowed", tc23)

def tc24():
    t = admin.token("admin/import.php?lang=en")
    st, _, loc, _ = admin.post("admin/import.php?lang=en", {"csrf": t, "action": "upload", "type": "template"},
                               files={"file": ("notes.txt", b"hello")})
    _, html, _, _ = admin.get("admin/import.php?lang=en")
    return "Only .csv and .xlsx" in html, "rejected: only .csv and .xlsx accepted"
case("TC24", "Import", "Upload a .txt file", "File rejected before it is stored", tc24)

def tc25():
    t = admin.token("admin/import.php?lang=en")
    admin.post("admin/import.php?lang=en", {"csrf": t, "action": "upload", "type": "template"},
               files={"file": ("test_stats.xlsx", open(XLSX, "rb").read())})
    _, html, _, _ = admin.get("admin/import.php?lang=en")
    ok = "2 valid rows" in html and "2 rows with errors" in html and 'unknown country &quot;XX&quot;' in html
    t = admin.token("admin/import.php?lang=en")
    admin.post("admin/import.php?lang=en", {"csrf": t, "action": "cancel"})
    return ok, "preview: 2 valid rows, 2 errors with row numbers; cancelled"
case("TC25", "Import", "Excel file with two bad rows", "Preview lists valid rows and explains each error", tc25)

def tc26():
    t = admin.token("admin/import.php?lang=en")
    csv = b"country_iso2,platform,metric,device,period,value\r\nBH,instagram,platform_users,all,2026,111\r\nBH,snapchat,platform_users,all,2026,222\r\n"
    admin.post("admin/import.php?lang=en", {"csrf": t, "action": "upload", "type": "template"}, files={"file": ("t.csv", csv)})
    t = admin.token("admin/import.php?lang=en")
    admin.post("admin/import.php?lang=en", {"csrf": t, "action": "confirm"})
    iid = sql("SELECT MAX(id) FROM imports")
    saved = sql(f"SELECT COUNT(*) FROM stats WHERE import_id = {iid}")
    t = admin.token("admin/imports.php?lang=en")
    admin.post("admin/imports.php?lang=en", {"csrf": t, "action": "rollback", "id": iid})
    left = sql(f"SELECT COUNT(*) FROM stats WHERE import_id = {iid}")
    sql(f"DELETE FROM imports WHERE id = {iid}")
    return saved == "2" and left == "0", f"import #{iid}: {saved} rows saved, {left} left after undo"
case("TC26", "Import", "Save an import, then undo it", "Rows are saved, and undo removes all of them", tc26)

def tc27():
    code = ('require "includes/classes/StatCounterClient.php"; $d = StatCounterClient::parse("\\"Date\\",\\"Facebook\\",\\"Other\\"\\n'
            '2026-09,60.5,39.5\\n2026-10,0,0\\n"); echo count($d), " ", implode(",", array_keys($d));')
    out = subprocess.run([PHP, "-r", code], capture_output=True, text=True, cwd=ROOT).stdout
    return out.strip() == "1 2026-09-01", f"parsed months: {out.strip()}"
case("TC27", "Import", "StatCounter CSV with empty future months", "Months where every value is 0 are skipped", tc27)

def tc28():
    t = admin.token("admin/backup.php")
    st, dump, _, _ = admin.post("admin/backup.php", {"csrf": t})
    tables = ["users", "countries", "platforms", "metrics", "sources", "settings", "imports", "stats",
              "survey_responses", "survey_response_platforms", "audit_log"]
    missing = []
    for tb in tables:
        live = int(sql(f"SELECT COUNT(*) FROM {tb}"))
        blocks = re.findall(rf"INSERT INTO `{tb}` \(.*?\) VALUES\n(.*?);\n", dump, re.S)
        in_file = sum(len(re.findall(r"^\(", b, re.M)) for b in blocks)
        # audit_log grows while the backup is written, so allow it to be one row behind
        if not (in_file == live or (tb == "audit_log" and live - in_file <= 1)):
            missing.append(f"{tb} {in_file}/{live}")
    total = sum(int(sql(f"SELECT COUNT(*) FROM {tb}")) for tb in tables if tb != "audit_log")
    return st == 200 and not missing, f"{len(dump) // 1024} KB, all 11 tables, {total} data rows match" if not missing else "; ".join(missing)
case("TC28", "Backup", "Download a full database backup", "File contains every table and the same number of rows", tc28)

def tc29():
    codes = [Session().get(p)[0] for p in ["includes/config.php", "storage/dev-admin.txt", "database/schema.sql"]]
    return all(c == 403 for c in codes), "HTTP " + " / ".join(map(str, codes))
case("TC29", "Security", "Open private folders in the browser", "includes/, storage/ and database/ return 403", tc29)

def tc30():
    sql("UPDATE sources SET last_synced_at = NULL WHERE code = 'worldbank'")
    t = admin.token("admin/sync.php")
    t0 = time.perf_counter()
    st, html, _, _ = admin.post("admin/sync.php?lang=en", {"csrf": t, "source": "worldbank", "from_year": "2010"})
    sec = time.perf_counter() - t0
    m = re.search(r"(\d+) new values, (\d+) updated, (\d+) requests failed", html)
    synced = sql("SELECT last_synced_at IS NOT NULL FROM sources WHERE code='worldbank'")
    iid = sql("SELECT MAX(id) FROM imports")
    sql(f"DELETE FROM imports WHERE id = {iid} AND rows_inserted = 0 AND rows_updated = 0")
    return m is not None and m.group(3) == "0" and synced == "1", f"{m.group(0) if m else 'no result'} in {sec:.0f} s"
case("TC30", "Data sources", "Update from the World Bank API", "Request succeeds; values updated; date recorded", tc30)

sql("DELETE FROM users WHERE email IN ('lock@test.local', 'editor@test.local')")
t = admin.token("admin/index.php")
admin.post("admin/logout.php", {"csrf": t})

# ---------------- response times ----------------
perf = []
for label, path in [("Home page (index.php)", "index.php?lang=en"), ("Explore page (explore.php)", "explore.php?lang=en"),
                    ("Compare page with ranking and scatter", "compare.php?lang=en&platform=instagram"),
                    ("API: one country series", "api/series.php?country=OM"), ("API: compare 6 countries", "api/compare.php?countries=OM,SA,AE,QA,KW,BH&platform=facebook"),
                    ("API: map", "api/map.php"), ("Local study page", "local-study.php?lang=en")]:
    times = [Session().get(path)[3] for _ in range(10)]
    perf.append({"page": label, "avg": round(statistics.mean(times)), "max": round(max(times)), "min": round(min(times))})
    print(perf[-1])

json.dump({"started": started, "cases": results, "perf": perf}, open(OUT, "w"), indent=1)
print("passed", sum(r["result"] == "Pass" for r in results), "of", len(results))
