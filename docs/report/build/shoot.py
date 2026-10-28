"""Full-page screenshots of every page for the report (uses the installed Google Chrome)."""
import os
import sys
import time
from playwright.sync_api import sync_playwright

BASE = "http://localhost/social-statistics"
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
OUT = sys.argv[1]
SAMPLE_XLSX = sys.argv[2]
os.makedirs(OUT, exist_ok=True)

creds = dict(line.split(": ", 1) for line in open(os.path.join(ROOT, "storage", "dev-admin.txt")).read().splitlines()[1:])

public = [
    ("s01_home", "index.php?lang=en"),
    ("s02_home_ar", "index.php?lang=ar"),
    ("s03_explore", "explore.php?lang=en&country=OM&metric=traffic_share&device=all"),
    ("s04_explore_wb", "explore.php?lang=en&country=OM&metric=internet_users"),
    ("s05_compare", "compare.php?lang=en&platform=instagram&countries=OM,SA,AE,QA,KW,BH"),
    ("s06_study", "local-study.php?lang=en"),
    ("s07_survey", "survey.php?lang=en"),
    ("s08_about", "about.php?lang=en"),
]
admin = [
    ("a02_dashboard", "admin/index.php?lang=en"),
    ("a03_sync", "admin/sync.php?lang=en"),
    ("a04_import", "admin/import.php?lang=en"),
    ("a06_imports", "admin/imports.php?lang=en"),
    ("a07_data", "admin/data.php?lang=en&country=OM&metric=traffic_share&device=all"),
    ("a08_survey", "admin/survey.php?lang=en"),
    ("a09_users", "admin/users.php?lang=en"),
    ("a10_log", "admin/log.php?lang=en"),
    ("a11_backup", "admin/backup.php?lang=en"),
]


def shot(page, name, url, full=True, wait=1.6):
    page.goto(f"{BASE}/{url}", wait_until="networkidle")
    time.sleep(wait)  # let Chart.js finish its animation
    page.screenshot(path=os.path.join(OUT, name + ".png"), full_page=full)
    print("saved", name)


with sync_playwright() as p:
    browser = p.chromium.launch(channel="chrome", headless=True)
    ctx = browser.new_context(viewport={"width": 1366, "height": 860}, device_scale_factor=1.5)
    page = ctx.new_page()
    for name, url in public:
        shot(page, name, url)

    # phone width
    mob = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2, is_mobile=True)
    mp = mob.new_page()
    shot(mp, "s09_home_mobile", "index.php?lang=en", full=False)
    shot(mp, "s10_home_mobile_ar", "index.php?lang=ar", full=False)
    mob.close()

    # admin: sign in first
    shot(page, "a01_login", "admin/login.php?lang=en", wait=0.3)
    page.fill("#email", creds["email"])
    page.fill("#password", creds["password"])
    page.click("button.btn-primary")
    page.wait_for_url("**/admin/index.php")
    for name, url in admin:
        shot(page, name, url, wait=0.4)

    # import preview with a file that contains two bad rows, then cancel
    page.goto(f"{BASE}/admin/import.php?lang=en")
    page.check("#type-template")
    page.set_input_files("#file", SAMPLE_XLSX)
    page.click("button:has-text('Upload and preview')")
    page.wait_for_load_state("networkidle")
    page.screenshot(path=os.path.join(OUT, "a05_import_preview.png"), full_page=True)
    print("saved a05_import_preview")
    page.click("button:has-text('Cancel')")

    # sign out so the session is not left open
    page.click("button:has-text('Sign out')")
    browser.close()
