"""Figure: Use case diagram."""
import sys
from uml import Canvas

OUT = sys.argv[1]
c = Canvas(1320, 900)

# system boundary
c.rect(235, 20, 830, 860, "none", lw=1.3, z=1)
c.text(650, 40, "Social Media Statistics Observatory", size=11, weight="bold")

# actors
c.actor(110, 210, "Visitor")
c.actor(110, 600, "Survey\nrespondent")
c.actor(1200, 250, "Data editor")
c.actor(1200, 560, "Administrator")
c.arrow([(110, 590), (110, 300)], kind="hollow", size=13)          # respondent is a visitor
c.arrow([(1200, 550), (1200, 340)], kind="hollow", size=13)        # administrator is a data editor

# external systems
for i, name in enumerate(["StatCounter", "World Bank API"]):
    y = 40 + i * 62
    c.rect(1110, y, 180, 44, "white", lw=1.1)
    c.text(1200, y + 13, "«external system»", size=7.5, style="italic")
    c.text(1200, y + 29, name, size=9, weight="bold")

L = {}
L["home"] = c.usecase(440, 100, "View home dashboard")
L["explore"] = c.usecase(440, 175, "Explore statistics\nwith filters", h=46)
L["download"] = c.usecase(440, 290, "Download data\n(CSV / PNG)", h=46)
L["compare"] = c.usecase(440, 370, "Compare countries")
L["study"] = c.usecase(440, 440, "View local study")
L["lang"] = c.usecase(440, 510, "Switch language\n(English / Arabic)", h=46)
L["survey"] = c.usecase(440, 640, "Answer questionnaire")

R = {}
R["login"] = c.usecase(880, 100, "Log in")
R["sync"] = c.usecase(880, 170, "Update data from APIs")
R["upload"] = c.usecase(880, 245, "Upload Excel / CSV file")
R["preview"] = c.usecase(680, 320, "Preview and\nvalidate rows", h=46)
R["undo"] = c.usecase(880, 395, "Undo an import")
R["data"] = c.usecase(880, 460, "Manage stored data")
R["qmanage"] = c.usecase(880, 525, "Manage questionnaire")
R["accounts"] = c.usecase(880, 620, "Manage accounts")
R["log"] = c.usecase(880, 690, "View audit log")
R["backup"] = c.usecase(880, 760, "Back up database")

visitor = (128, 238)
for k in ["home", "explore", "compare", "study", "lang"]:
    c.line([visitor, c.ellipse_point(L[k], visitor)])
resp = (128, 628)
c.line([resp, c.ellipse_point(L["survey"], resp)])

editor = (1180, 278)
for k in ["login", "sync", "upload", "undo", "data", "qmanage"]:
    c.line([editor, c.ellipse_point(R[k], editor)])
admin = (1180, 588)
for k in ["accounts", "log", "backup"]:
    c.line([admin, c.ellipse_point(R[k], admin)])

# external systems take part in "Update data from APIs"
for y in (62, 124):
    c.line([(1110, y), c.ellipse_point(R["sync"], (1110, y))])

# include / extend
a = c.ellipse_point(R["upload"], (R["preview"]["x"], R["preview"]["y"]))
b = c.ellipse_point(R["preview"], (R["upload"]["x"], R["upload"]["y"]))
c.arrow([a, b], kind="open", dashed=True, label="«include»", label_at=(790, 270))
a = c.ellipse_point(L["download"], (L["explore"]["x"], L["explore"]["y"]))
b = c.ellipse_point(L["explore"], (L["download"]["x"], L["download"]["y"]))
c.arrow([a, b], kind="open", dashed=True, label="«extend»", label_at=(482, 232))

c.note(560, 790, 300, "Data editor use cases need a signed-in\nsession; accounts, audit log and backup\nare limited to the Administrator role.")
c.save(OUT)
