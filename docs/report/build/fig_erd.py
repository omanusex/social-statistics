"""Figure: Entity Relationship Diagram (crow's foot) of the social_stats database."""
import sys
from uml import Canvas

OUT = sys.argv[1]
c = Canvas(1430, 900)

E = {}
E["countries"] = c.entity(30, 40, "countries", [
    ("PK", "id", "INT"), ("UQ", "iso2", "CHAR(2)"), ("", "iso3", "CHAR(3)"), ("", "name_en", "VARCHAR(80)"),
    ("", "name_ar", "VARCHAR(80)"), ("", "region", "ENUM"), ("", "is_default", "TINYINT"), ("", "is_active", "TINYINT")], w=235)
E["metrics"] = c.entity(30, 300, "metrics", [
    ("PK", "id", "INT"), ("UQ", "code", "VARCHAR(40)"), ("", "name_en", "VARCHAR(100)"), ("", "name_ar", "VARCHAR(100)"),
    ("", "unit", "VARCHAR(20)"), ("", "granularity", "ENUM"), ("", "per_platform", "TINYINT"),
    ("", "description_en", "TEXT"), ("", "description_ar", "TEXT")], w=235)
E["sources"] = c.entity(30, 600, "sources", [
    ("PK", "id", "INT"), ("UQ", "code", "VARCHAR(30)"), ("", "name", "VARCHAR(100)"), ("", "url", "VARCHAR(255)"),
    ("", "license", "VARCHAR(100)"), ("", "last_synced_at", "DATETIME")], w=235)
E["stats"] = c.entity(420, 150, "stats", [
    ("PK", "id", "BIGINT"), ("FK", "country_id", "INT"), ("FK", "platform_id", "INT NULL"), ("FK", "metric_id", "INT"),
    ("", "device", "ENUM"), ("", "period", "DATE"), ("", "value", "DECIMAL(16,2)"), ("FK", "source_id", "INT"),
    ("FK", "import_id", "INT NULL"), ("", "updated_at", "DATETIME"), ("", "platform_key", "INT (generated)")], w=245)
E["imports"] = c.entity(420, 570, "imports", [
    ("PK", "id", "INT"), ("FK", "user_id", "INT NULL"), ("FK", "source_id", "INT"), ("", "type", "ENUM"),
    ("", "filename", "VARCHAR(255)"), ("", "rows_inserted", "INT"), ("", "rows_updated", "INT"), ("", "rows_failed", "INT"),
    ("", "status", "ENUM"), ("", "message", "TEXT"), ("", "created_at", "DATETIME")], w=245)
E["platforms"] = c.entity(800, 30, "platforms", [
    ("PK", "id", "INT"), ("UQ", "slug", "VARCHAR(40)"), ("", "name", "VARCHAR(60)"), ("", "statcounter_name", "VARCHAR(60)"),
    ("", "color", "CHAR(7)"), ("", "sort_order", "SMALLINT")], w=245)
E["responses"] = c.entity(800, 300, "survey_responses", [
    ("PK", "id", "INT"), ("", "age_group", "ENUM"), ("", "gender", "ENUM"), ("FK", "favourite_id", "INT NULL"),
    ("", "main_purpose", "ENUM"), ("", "daily_hours", "ENUM"), ("", "late_night_use", "TINYINT"), ("", "origin", "ENUM"),
    ("FK", "import_id", "INT NULL"), ("", "created_at", "DATETIME")], w=245)
E["users"] = c.entity(800, 640, "users", [
    ("PK", "id", "INT"), ("", "name", "VARCHAR(100)"), ("UQ", "email", "VARCHAR(150)"), ("", "password_hash", "VARCHAR(255)"),
    ("", "role", "ENUM"), ("", "failed_logins", "TINYINT"), ("", "locked_until", "DATETIME"), ("", "last_login_at", "DATETIME"),
    ("", "created_at", "DATETIME")], w=245)
E["srp"] = c.entity(1165, 110, "survey_response_platforms", [
    ("PK,FK", "response_id", "INT"), ("PK,FK", "platform_id", "INT")], w=235)
E["settings"] = c.entity(1165, 330, "settings", [("PK", "name", "VARCHAR(50)"), ("", "value", "VARCHAR(255)")], w=235)
E["audit"] = c.entity(1165, 640, "audit_log", [
    ("PK", "id", "BIGINT"), ("FK", "user_id", "INT NULL"), ("", "action", "VARCHAR(50)"), ("", "details", "VARCHAR(500)"),
    ("", "ip_address", "VARCHAR(45)"), ("", "created_at", "DATETIME")], w=235)

st, im = E["stats"], E["imports"]
R = lambda e: e["x"] + e["w"]

# countries 1 --- * stats
c.relation([(R(E["countries"]), 120), (340, 120), (340, 210), (st["x"], 210)], "one", "zero_many")
# metrics 1 --- * stats
c.relation([(R(E["metrics"]), 400), (340, 400), (340, 300), (st["x"], 300)], "one", "zero_many")
# sources 1 --- * stats
c.relation([(R(E["sources"]), 650), (378, 650), (378, 360), (st["x"], 360)], "one", "zero_many")
# sources 1 --- * imports
c.relation([(R(E["sources"]), 712), (im["x"], 712)], "one", "zero_many")
# imports 0..1 --- * stats
c.relation([(540, im["y"]), (540, st["y"] + st["h"])], "zero_one", "zero_many")
# platforms 0..1 --- * stats
c.relation([(E["platforms"]["x"], 100), (735, 100), (735, 245), (R(st), 245)], "zero_one", "zero_many")
# platforms 0..1 --- * survey_responses (favourite)
c.relation([(920, E["platforms"]["y"] + E["platforms"]["h"]), (920, E["responses"]["y"])], "zero_one", "zero_many",
           "favourite", (958, 245))
# platforms 1 --- * survey_response_platforms
c.relation([(R(E["platforms"]), 140), (E["srp"]["x"], 140)], "one", "zero_many")
# survey_responses 1 --- * survey_response_platforms
c.relation([(R(E["responses"]), 360), (1105, 360), (1105, 172), (E["srp"]["x"], 172)], "one", "many")
# imports 0..1 --- * survey_responses
c.relation([(R(im), 700), (760, 700), (760, 480), (E["responses"]["x"], 480)], "zero_one", "zero_many")
# users 0..1 --- * imports
c.relation([(E["users"]["x"], 770), (R(im), 770)], "zero_one", "zero_many")
# users 0..1 --- * audit_log
c.relation([(R(E["users"]), 700), (E["audit"]["x"], 700)], "zero_one", "zero_many")

# legend
lx, ly = 1165, 440
c.rect(lx, ly, 235, 150, "none", lw=0.8)
c.text(lx + 10, ly + 14, "Notation (crow's foot)", size=8.5, weight="bold", ha="left")
for i, (kind, label) in enumerate([("one", "exactly one"), ("zero_one", "zero or one"),
                                   ("many", "one or many"), ("zero_many", "zero or many")]):
    y = ly + 40 + i * 26
    c.line([(lx + 15, y), (lx + 75, y)], lw=1.1)
    c.crow_end((lx + 75, y), (lx + 15, y), kind)
    c.text(lx + 90, y, label, size=8.5, ha="left")
c.text(lx + 10, ly + 140, "PK primary key · FK foreign key · UQ unique", size=7.5, ha="left", color="#444444")

c.save(OUT)
