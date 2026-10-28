"""Figures: three-tier architecture and site map."""
import sys
import os
from uml import Canvas

OUT = sys.argv[1]


def tier(c, x, y, w, h, title, sub):
    c.rect(x, y, w, h, "#f6f8fb", lw=1.3)
    c.text(x + 12, y + 16, title, size=10.5, weight="bold", ha="left")
    c.text(x + 12, y + 32, sub, size=8, ha="left", color="#555555")


def comp(c, x, y, w, h, title, lines, fill="white"):
    c.rect(x, y, w, h, fill, lw=1.0)
    # small UML component icon
    ix, iy = x + w - 24, y + 8
    c.rect(ix, iy, 16, 18, "white", lw=0.8, z=5)
    c.rect(ix - 4, iy + 4, 8, 4, "white", lw=0.8, z=6)
    c.rect(ix - 4, iy + 11, 8, 4, "white", lw=0.8, z=6)
    c.text(x + 10, y + 16, title, size=9, weight="bold", ha="left")
    for i, l in enumerate(lines):
        c.text(x + 10, y + 34 + i * 13, l, size=8, ha="left")


def architecture():
    c = Canvas(1300, 900)
    # client tier
    tier(c, 30, 20, 880, 170, "Client tier: web browser", "Desktop or mobile, English (LTR) or Arabic (RTL)")
    comp(c, 50, 65, 260, 105, "HTML pages", ["Bootstrap 5.3 (LTR / RTL builds)", "Bootstrap Icons, Cairo / Source Sans 3", "Forms with CSRF token"])
    comp(c, 330, 65, 270, 105, "JavaScript", ["app.js: shared chart helpers", "Chart.js 4.4: line, bar, scatter", "jsVectorMap 1.5: world map"])
    comp(c, 620, 65, 270, 105, "Downloads", ["CSV export of the data table", "PNG image of a chart", "Shareable link with filters"])

    # application tier
    tier(c, 30, 260, 880, 360, "Application tier: Apache 2.4 + PHP 8.2 (XAMPP)", "Server-side rendering plus a small JSON API")
    comp(c, 50, 305, 260, 120, "Public pages", ["index.php, explore.php", "compare.php, local-study.php", "survey.php, about.php", "install.php (one time)"])
    comp(c, 330, 305, 270, 120, "Admin pages (admin/)", ["login, dashboard, sync, import", "imports, data, survey, users", "log and backup (admin role)"])
    comp(c, 620, 305, 270, 120, "JSON API (api/)", ["series.php: one country", "compare.php: several countries", "map.php: leading platform"])
    comp(c, 50, 445, 840, 155, "Shared code (includes/)", [
        "Database (PDO)  ·  Auth (sessions, roles, lock-out)  ·  Lang (en.php / ar.php)  ·  Settings  ·  AuditLog",
        "StatRepository  ·  SurveyRepository  ·  InsightEngine (written findings)  ·  BackupService",
        "DataImporter  ·  SpreadsheetReader (CSV / XLSX)  ·  StatCounterClient  ·  WorldBankClient  ·  HttpClient (cURL)",
        "",
        "helpers.php: escaping (XSS), CSRF tokens, flash messages, formatting",
        "header.php / footer.php / admin_header.php: page layout"])

    # data tier
    tier(c, 30, 700, 560, 175, "Data tier", "")
    comp(c, 50, 735, 310, 120, "MySQL / MariaDB 10.4", ["database social_stats, utf8mb4", "11 tables, foreign keys, unique keys", "prepared statements only"], fill="#fdfbe8")
    comp(c, 380, 735, 190, 120, "storage/", ["uploads/ (temporary)", "backups/ (.sql)", "installed.lock", "blocked by .htaccess"], fill="#fdfbe8")

    # external services
    tier(c, 980, 260, 300, 360, "External data sources", "Read over HTTPS with cURL")
    comp(c, 1000, 305, 260, 120, "StatCounter Global Stats", ["chart.php?csv=1", "monthly social media share", "per country and device", "licence CC BY-SA 3.0"])
    comp(c, 1000, 445, 260, 120, "World Bank API v2", ["/country/{iso3}/indicator/{code}", "IT.NET.USER.ZS, SP.POP.TOTL", "JSON, no API key", "licence CC BY 4.0"])
    comp(c, 980, 700, 300, 120, "Excel / CSV files", ["StatCounter CSV downloads", "statistics template", "questionnaire answers", "(uploaded by a data editor)"])

    # connections
    c.arrow([(300, 190), (300, 260)], kind="filled", size=9)
    c.arrow([(340, 260), (340, 190)], kind="filled", size=9)
    c.text(205, 225, "HTTP GET / POST", size=8, style="italic", bg="white")
    c.text(460, 225, "HTML pages", size=8, style="italic", bg="white")
    c.arrow([(700, 190), (700, 260)], kind="filled", size=9)
    c.arrow([(740, 260), (740, 190)], kind="filled", size=9)
    c.text(620, 225, "fetch()", size=8, style="italic", bg="white")
    c.text(810, 225, "JSON", size=8, style="italic", bg="white")
    c.arrow([(200, 620), (200, 700)], kind="filled", size=9)
    c.arrow([(240, 700), (240, 620)], kind="filled", size=9)
    c.text(140, 660, "PDO / SQL", size=8, style="italic", bg="white")
    c.text(300, 660, "result rows", size=8, style="italic", bg="white")
    c.arrow([(475, 620), (475, 700)], kind="filled", size=9)
    c.text(535, 660, "files", size=8, style="italic", bg="white")
    c.arrow([(910, 365), (980, 365)], kind="filled", size=9)
    c.arrow([(910, 505), (980, 505)], kind="filled", size=9)
    c.text(945, 352, "CSV", size=8, style="italic", bg="white")
    c.text(945, 492, "JSON", size=8, style="italic", bg="white")
    c.arrow([(1130, 700), (1130, 640), (910, 640), (910, 600)], kind="filled", size=9)
    c.text(1020, 628, "upload (multipart form)", size=8, style="italic", bg="white")
    c.save(os.path.join(OUT, "fig4_architecture.png"))


def box(c, x, y, w, label, sub=None, fill="white", bold=False):
    h = 44 if sub else 32
    c.rect(x - w / 2, y, w, h, fill, lw=1.1)
    c.text(x, y + (14 if sub else 16), label, size=9, weight="bold" if bold else "normal")
    if sub:
        c.text(x, y + 31, sub, size=7.5, color="#555555")
    return (x, y, h)


def sitemap():
    c = Canvas(1300, 560)
    # public part
    c.rect(20, 20, 640, 520, "none", lw=1.0, dashed=True, z=1)
    c.text(35, 36, "Public site (no sign-in)", size=9.5, weight="bold", ha="left")
    home = box(c, 340, 60, 200, "Home", "index.php", fill="#dce6f2", bold=True)
    kids = [("Explore", "explore.php"), ("Compare", "compare.php"), ("Local Study", "local-study.php"), ("About", "about.php")]
    xs = [100, 260, 420, 580]
    c.line([(340, 104), (340, 140)])
    c.line([(xs[0], 140), (xs[-1], 140)])
    for x, (l, s) in zip(xs, kids):
        c.arrow([(x, 140), (x, 170)], size=8)
        box(c, x, 170, 140, l, s)
    c.arrow([(420, 214), (420, 260)], size=8)
    box(c, 420, 260, 150, "Questionnaire", "survey.php")
    for x, l in [(100, "CSV / PNG download,\nshareable link"), (260, "ranking table,\nscatter chart")]:
        c.line([(x, 214), (x, 260)], dashed=True)
        c.note(x - 70, 260, 140, l)
    c.text(340, 380, "Every page: language switch (English / Arabic) and link to Admin", size=8.5, style="italic")
    c.rect(150, 420, 380, 90, "white", lw=1.0)
    c.text(340, 437, "JSON API used by the charts", size=9, weight="bold")
    c.text(340, 458, "api/series.php  ·  api/compare.php  ·  api/map.php", size=8.5)
    c.text(340, 480, "read only, no sign-in, input validated", size=8, color="#555555")

    # admin part
    c.rect(690, 20, 590, 520, "none", lw=1.0, dashed=True, z=1)
    c.text(705, 36, "Admin panel (signed-in users only)", size=9.5, weight="bold", ha="left")
    box(c, 985, 60, 180, "Sign in", "admin/login.php", fill="#dce6f2", bold=True)
    c.arrow([(985, 104), (985, 130)], size=8)
    box(c, 985, 130, 180, "Dashboard", "admin/index.php", fill="#dce6f2", bold=True)
    c.line([(985, 174), (985, 200)])
    items = [("Update from APIs", "sync.php"), ("Upload file", "import.php"), ("Import history", "imports.php"),
             ("Stored data", "data.php"), ("Questionnaire", "survey.php"), ("Accounts", "users.php"),
             ("Audit log *", "log.php"), ("Backup *", "backup.php")]
    c.line([(760, 200), (1210, 200)])
    for i, (l, s) in enumerate(items):
        col, row = i % 4, i // 4
        x = 760 + col * 150
        y = 230 + row * 90
        if row == 0:
            c.arrow([(x, 200), (x, y)], size=8)
        box(c, x, y, 136, l, "admin/" + s)
    # second row hangs from a second bus line
    c.line([(985, 200), (985, 300)])
    c.line([(760, 300), (1210, 300)])
    for col in range(4):
        x = 760 + col * 150
        c.arrow([(x, 300), (x, 320)], size=8)
    c.text(985, 420, "* administrator role only", size=8.5, style="italic")
    c.text(985, 440, "Sign out is a POST form in the top bar of every admin page", size=8.5, style="italic")
    c.save(os.path.join(OUT, "fig4_sitemap.png"))


architecture()
sitemap()
