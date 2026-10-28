"""Figures: sequence diagrams and activity diagrams."""
import sys
import os
from uml import Canvas, LINE

OUT = sys.argv[1]


def seq_explore():
    c = Canvas(1120, 840)
    X = {"v": 70, "b": 240, "e": 440, "a": 640, "s": 840, "d": 1030}
    top, bottom = 30, 820
    c.lifeline(X["v"], top + 6, bottom, "Visitor", kind="actor")
    for k, n in [("b", "Browser"), ("e", "explore.php"), ("a", "api/series.php"), ("s", "StatRepository"), ("d", "MySQL")]:
        c.lifeline(X[k], top, bottom, n)
    for k, a, b in [("b", 140, 800), ("e", 190, 390), ("s", 230, 350), ("d", 270, 310), ("a", 440, 690), ("s", 480, 600), ("d", 520, 560)]:
        c.activation(X[k], a, b)
    m = c.message
    m(X["v"], X["b"], 150, "choose filters, click Apply", num=1)
    m(X["b"], X["e"], 190, "GET explore.php?filters", num=2)
    m(X["e"], X["s"], 230, "metrics(true), platformsWithData()", num=3)
    m(X["s"], X["d"], 270, "prepared SELECT", num=4)
    m(X["d"], X["s"], 310, "rows", ret=True, num=5)
    m(X["s"], X["e"], 350, "statistic types, platforms", ret=True, num=6)
    m(X["e"], X["b"], 390, "HTML page with filter form", ret=True, num=7)
    m(X["b"], X["a"], 440, "fetch(series.php?filters)", num=8)
    m(X["a"], X["s"], 480, "series(country, metric, device, from, to)", num=9)
    m(X["s"], X["d"], 520, "SELECT period, platform_id, value", num=10)
    m(X["d"], X["s"], 560, "rows", ret=True, num=11)
    m(X["s"], X["a"], 600, "rows", ret=True, num=12)
    m(X["a"], X["a"], 625, "12a: fill empty months, 3-month average")
    m(X["a"], X["b"], 690, "JSON {labels, datasets, source}", ret=True, num=13)
    m(X["b"], X["b"], 715, "14: draw Chart.js chart and data table")
    m(X["b"], X["v"], 780, "chart, table, CSV / PNG buttons", ret=True, num=15)
    c.save(os.path.join(OUT, "fig4_seq_explore.png"))


def seq_sync():
    c = Canvas(1260, 760)
    X = {"u": 70, "p": 230, "i": 420, "c": 620, "w": 820, "r": 1000, "d": 1170}
    top, bottom = 30, 740
    c.lifeline(X["u"], top + 6, bottom, "Administrator", kind="actor")
    for k, n in [("p", "sync.php"), ("i", "DataImporter"), ("c", "StatCounterClient"), ("w", "StatCounter\nserver"),
                 ("r", "StatRepository"), ("d", "MySQL")]:
        c.lifeline(X[k], top, bottom, n)
    for k, a, b in [("p", 140, 700), ("i", 220, 660), ("c", 290, 410), ("w", 330, 370), ("r", 480, 560), ("d", 520, 545), ("d", 600, 625)]:
        c.activation(X[k], a, b)
    m = c.message
    m(X["u"], X["p"], 150, "POST countries, devices, period", num=1)
    m(X["p"], X["p"], 170, "2: verify_csrf(), Auth::require()")
    m(X["p"], X["i"], 220, "syncStatCounter(countries, devices, from, to)", num=3)
    c.fragment(330, 255, 560, 175, "loop", "[for each country and device]")
    m(X["i"], X["c"], 290, "fetchCsv(iso2, device, from, to)", num=4)
    m(X["c"], X["w"], 330, "HTTPS GET chart.php?csv=1", num=5)
    m(X["w"], X["c"], 370, "CSV text", ret=True, num=6)
    m(X["c"], X["i"], 410, "parse(csv): months and shares", ret=True, num=7)
    c.fragment(330, 450, 900, 125, "loop", "[for each record, inside one transaction]")
    m(X["i"], X["r"], 490, "upsert(country, platform, metric, device, period, value)", num=8)
    m(X["r"], X["d"], 525, "INSERT ... ON DUPLICATE KEY UPDATE", num=9)
    m(X["d"], X["r"], 548, "rowCount", ret=True, num=10)
    m(X["r"], X["i"], 560, "inserted | updated | unchanged", ret=True, num=11)
    m(X["i"], X["d"], 605, "UPDATE imports, INSERT audit_log", num=12)
    m(X["i"], X["p"], 660, "summary (inserted, updated, failed)", ret=True, num=13)
    m(X["p"], X["u"], 700, "result message", ret=True, num=14)
    c.save(os.path.join(OUT, "fig4_seq_sync.png"))


def seq_login():
    c = Canvas(1000, 880)
    X = {"u": 70, "l": 260, "a": 470, "d": 690, "g": 890}
    top, bottom = 30, 860
    c.lifeline(X["u"], top + 6, bottom, "Administrator", kind="actor")
    for k, n in [("l", "login.php"), ("a", "Auth"), ("d", "MySQL"), ("g", "AuditLog")]:
        c.lifeline(X[k], top, bottom, n)
    for k, a, b in [("l", 140, 840), ("a", 210, 780), ("d", 250, 290)]:
        c.activation(X[k], a, b)
    m = c.message
    m(X["u"], X["l"], 150, "POST email, password, csrf", num=1)
    m(X["l"], X["l"], 165, "2: verify_csrf()")
    m(X["l"], X["a"], 210, "attempt(email, password)", num=3)
    m(X["a"], X["d"], 250, "SELECT * FROM users WHERE email = ?", num=4)
    m(X["d"], X["a"], 290, "user row", ret=True, num=5)
    c.fragment(200, 320, 770, 500, "alt", "[account locked]",
               dividers=[(70, "[password_verify() is true]"), (300, "[wrong password]")])
    m(X["a"], X["l"], 360, "'login.locked'", ret=True, num=6)
    m(X["a"], X["d"], 425, "reset failed_logins, set last_login_at", num=7)
    m(X["a"], X["a"], 445, "8: session_regenerate_id(true)")
    m(X["a"], X["g"], 500, "write('login')", num=9)
    m(X["a"], X["l"], 540, "null (success)", ret=True, num=10)
    m(X["l"], X["u"], 580, "redirect to admin dashboard", ret=True, num=11)
    m(X["a"], X["d"], 655, "failed_logins + 1, lock after 5 attempts", num=12)
    m(X["a"], X["g"], 695, "write('login_failed')", num=13)
    m(X["a"], X["l"], 735, "'login.invalid'", ret=True, num=14)
    m(X["l"], X["u"], 780, "error message", ret=True, num=15)
    c.save(os.path.join(OUT, "fig4_seq_login.png"))


def flow(c, pts, label=None, at=None):
    c.arrow(pts, kind="open", size=9)
    if label:
        c.text(*at, label, size=8, style="italic", bg="white")


def lanes(c, names, xs, height):
    for (name, x0, x1) in zip(names, xs[:-1], xs[1:]):
        c.rect(x0, 10, x1 - x0, height - 20, "none", lw=1.1, z=1)
        c.rect(x0, 10, x1 - x0, 32, "#e9eef5", lw=1.1, z=1)
        c.text((x0 + x1) / 2, 26, name, size=10, weight="bold")


def act_import():
    c = Canvas(900, 1180)
    lanes(c, ["Administrator", "System"], [20, 330, 880], 1180)
    A, S = 175, 605
    c.start(A, 70)
    a1 = c.action(A, 135, "Choose file type and file,\nclick \"Upload and preview\"")
    flow(c, [(A, 79), (A, a1["y"] - a1["h"] / 2)])
    s1 = c.action(S, 135, "Check CSRF token, file size,\nextension and ZIP signature")
    flow(c, [(A + a1["w"] / 2, 135), (S - s1["w"] / 2, 135)])
    c.decision(S, 220)
    flow(c, [(S, 135 + s1["h"] / 2), (S, 202)])
    e1 = c.action(S + 175, 220, "Show error\nmessage", w=110)
    flow(c, [(S + 18, 220), (e1["x"] - e1["w"] / 2, 220)], "[invalid]", (S + 75, 208))
    flow(c, [(e1["x"], 220 - e1["h"] / 2), (e1["x"], 92), (A + 40, 92), (A + 40, 135 - a1["h"] / 2)])
    s2 = c.action(S, 300, "Store file under a random name\nin storage/uploads")
    flow(c, [(S, 238), (S, 300 - s2["h"] / 2)], "[valid]", (S - 30, 258))
    s3 = c.action(S, 380, "Read CSV / XLSX and validate\nevery row (codes, numbers, periods)")
    flow(c, [(S, 300 + s2["h"] / 2), (S, 380 - s3["h"] / 2)])
    s4 = c.action(S, 460, "Show preview: valid rows,\nrow errors, first 10 rows")
    flow(c, [(S, 380 + s3["h"] / 2), (S, 460 - s4["h"] / 2)])
    c.decision(A, 545)
    flow(c, [(S, 460 + s4["h"] / 2), (S, 510), (A, 510), (A, 527)])
    a2 = c.action(A, 640, "Delete temporary file", w=170)
    flow(c, [(A, 563), (A, 640 - a2["h"] / 2)], "[cancel]", (A - 40, 595))
    c.end(A, 720)
    flow(c, [(A, 640 + a2["h"] / 2), (A, 709)])
    s5 = c.action(S, 620, "Begin transaction, upsert rows\n(or insert questionnaire answers)")
    flow(c, [(A + 18, 545), (S, 545), (S, 620 - s5["h"] / 2)], "[confirm]", (A + 120, 533))
    c.decision(S, 715)
    flow(c, [(S, 620 + s5["h"] / 2), (S, 697)])
    e2 = c.action(S + 170, 790, "Roll back, mark\nimport as failed", w=130)
    flow(c, [(S + 18, 715), (e2["x"], 715), (e2["x"], 790 - e2["h"] / 2)], "[error]", (S + 75, 703))
    s6 = c.action(S, 800, "Commit, record counts in\nimports, write audit_log")
    flow(c, [(S, 733), (S, 800 - s6["h"] / 2)], "[no error]", (S - 42, 760))
    s7 = c.action(S, 890, "Delete temporary file,\nopen Import history with summary")
    flow(c, [(S, 800 + s6["h"] / 2), (S, 890 - s7["h"] / 2)])
    flow(c, [(e2["x"], 790 + e2["h"] / 2), (e2["x"], 890), (S + s7["w"] / 2, 890)])
    a3 = c.action(A, 990, "Read summary; use \"Undo\"\nlater if needed")
    flow(c, [(S, 890 + s7["h"] / 2), (S, 960), (A + a3["w"] / 2 + 10, 960), (A + a3["w"] / 2 + 10, 990 - a3["h"] / 2 + 6)])
    c.end(A, 1080)
    flow(c, [(A, 990 + a3["h"] / 2), (A, 1069)])
    c.save(os.path.join(OUT, "fig4_act_import.png"))


def act_survey():
    c = Canvas(900, 1180)
    lanes(c, ["Respondent", "System"], [20, 330, 880], 1180)
    A, S = 175, 590
    c.start(A, 70)
    a1 = c.action(A, 130, "Open the questionnaire page")
    flow(c, [(A, 79), (A, 130 - a1["h"] / 2)])
    c.decision(S, 130)
    flow(c, [(A + a1["w"] / 2, 130), (S - 18, 130)])
    e0 = c.action(S + 175, 200, "Show \"closed\"\nmessage", w=120)
    flow(c, [(S + 18, 130), (e0["x"], 130), (e0["x"], 200 - e0["h"] / 2)], "[closed]", (S + 75, 118))
    c.end(e0["x"], 270)
    flow(c, [(e0["x"], 200 + e0["h"] / 2), (e0["x"], 259)])
    s1 = c.action(S, 215, "Show form with CSRF token\nand hidden honeypot field")
    flow(c, [(S, 148), (S, 215 - s1["h"] / 2)], "[open]", (S - 28, 168))
    a2 = c.action(A, 300, "Answer 7 questions,\ntick consent, submit")
    flow(c, [(S, 215 + s1["h"] / 2), (S, 258), (A, 258), (A, 300 - a2["h"] / 2)])
    s2 = c.action(S, 380, "Check CSRF token")
    flow(c, [(A, 300 + a2["h"] / 2), (A, 380), (S - s2["w"] / 2, 380)])
    c.decision(S, 455)
    flow(c, [(S, 380 + s2["h"] / 2), (S, 437)])
    e1 = c.action(S + 175, 520, "Redirect without\nsaving (bot)", w=125)
    flow(c, [(S + 18, 455), (e1["x"], 455), (e1["x"], 520 - e1["h"] / 2)], "[honeypot filled]", (S + 95, 443))
    c.end(e1["x"], 590)
    flow(c, [(e1["x"], 520 + e1["h"] / 2), (e1["x"], 579)])
    c.decision(S, 545)
    flow(c, [(S, 473), (S, 527)], "[empty]", (S - 30, 495))
    e2 = c.action(S + 175, 650, "Show \"already\nanswered\"", w=125)
    flow(c, [(S + 18, 545), (S + 60, 545), (S + 60, 650), (e2["x"] - e2["w"] / 2, 650)], "[already answered]", (S + 60, 600))
    c.end(e2["x"], 720)
    flow(c, [(e2["x"], 650 + e2["h"] / 2), (e2["x"], 709)])
    s3 = c.action(S - 60, 650, "Validate answers\n(favourite must be a\nplatform that is used)", w=170)
    flow(c, [(S, 563), (S, 585), (S - 60, 585), (S - 60, 650 - s3["h"] / 2)], "[first answer]", (S - 60, 608))
    c.decision(S - 60, 745)
    flow(c, [(S - 60, 650 + s3["h"] / 2), (S - 60, 727)])
    a3 = c.action(A, 745, "Read error messages;\nanswers are kept", w=170)
    flow(c, [(S - 78, 745), (A + a3["w"] / 2, 745)], "[invalid]", (S - 160, 733))
    flow(c, [(A - a3["w"] / 2, 745), (40, 745), (40, 300), (A - a2["w"] / 2, 300)])
    s4 = c.action(S - 60, 840, "Insert survey_responses and\nsurvey_response_platforms\nin one transaction")
    flow(c, [(S - 60, 763), (S - 60, 840 - s4["h"] / 2)], "[valid]", (S - 90, 790))
    s5 = c.action(S - 60, 935, "Mark session as answered,\nredirect with thank-you message")
    flow(c, [(S - 60, 840 + s4["h"] / 2), (S - 60, 935 - s5["h"] / 2)])
    a4 = c.action(A, 1030, "See updated Local Study\nresults")
    flow(c, [(S - 60, 935 + s5["h"] / 2), (S - 60, 1030), (A + a4["w"] / 2, 1030)])
    c.end(A, 1110)
    flow(c, [(A, 1030 + a4["h"] / 2), (A, 1099)])
    c.save(os.path.join(OUT, "fig4_act_survey.png"))


seq_explore()
seq_sync()
seq_login()
act_import()
act_survey()
