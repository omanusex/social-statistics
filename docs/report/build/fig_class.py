"""Figure: Class diagram of the PHP classes in includes/classes."""
import sys
from uml import Canvas

OUT = sys.argv[1]
c = Canvas(1720, 1060)

B = {}
# ---- row 1 ----
B["insight"] = c.class_box(30, 30, "InsightEngine", ["- country: array", "- metric: array"],
                           ["+ __construct(country: array)", "+ generate(): array", "- leader(period): ?array",
                            "- trend(period): ?array", "- devices(period): ?array", "- gcc(period): ?array",
                            "- internet(): ?array"])
B["importer"] = c.class_box(450, 30, "DataImporter", ["- userId: ?int", "+ TEMPLATE_COLUMNS: array", "+ SURVEY_COLUMNS: array"],
                            ["+ syncStatCounter(countries, devices, from, to): array", "+ syncWorldBank(fromYear, toYear): array",
                             "+ parseStatCounterFile(path, countryId, device): array", "+ parseTemplate(path, ext): array",
                             "+ parseSurvey(path, ext): array", "+ saveStats(records, source, type, file): array",
                             "+ saveSurvey(records, file, failed, replace): array", "+ rollback(importId): int"])
B["lang"] = c.class_box(1440, 30, "Lang", ["- current: string", "- strings: array"],
                        ["+ init(default): void", "+ current(): string", "+ dir(): string", "+ get(key, params): string"])

# ---- row 2 ----
y2 = 410
B["stat"] = c.class_box(30, y2, "StatRepository", ["+ DEVICES: array"],
                        ["+ countries(): array", "+ metrics(onlyWithData): array", "+ series(country, metric, device,",
                         "      from, to, platforms): array", "+ shares(country, metric, device, period): array",
                         "+ valuesByCountry(metric, platform, device, period)", "+ topPlatformPerCountry(metric, device, period)",
                         "+ upsert(country, platform, metric, device,", "      period, value, source, import): string",
                         "+ movingAverage(values): array", "+ correlation(x, y): ?float"])
B["survey"] = c.class_box(410, y2, "SurveyRepository", ["+ AGE_GROUPS: array", "+ PURPOSES: array", "+ HOURS: array"],
                          ["+ add(data, origin, importId): array", "+ validate(data): array", "+ summary(filters): array",
                           "+ count(filters): int", "+ export(): array", "+ deleteAll(): int"])
B["sheet"] = c.class_box(735, y2, "SpreadsheetReader", ["(no attributes)"],
                         ["+ read(path, ext): array", "+ readAssoc(path, ext): array", "- readCsv(path): array",
                          "- readXlsx(path): array", "- columnIndex(letters): int"])
B["sc"] = c.class_box(1000, y2, "StatCounterClient", ["- URL: string", "- DEVICE_PARAM: array"],
                      ["+ fetchCsv(iso2, device, from, to): string", "+ parse(csv): array"])
B["wb"] = c.class_box(1360, y2, "WorldBankClient", ["- URL: string", "+ INDICATORS: array"],
                      ["+ fetch(indicator, iso3Codes, from, to): array"])

# ---- row 3 ----
y3 = 760
B["settings"] = c.class_box(30, y3, "Settings", ["(no attributes)"], ["+ get(name, default): string", "+ set(name, value): void"])
B["backup"] = c.class_box(30, 860, "BackupService", ["- TABLES: array"], ["+ dump(): string"])
B["db"] = c.class_box(330, y3, "Database", ["- config: array", "- pdo: ?PDO"],
                      ["+ configure(config): void", "+ conn(): PDO", "+ connect(withDatabase): PDO",
                       "+ query(sql, params): PDOStatement", "+ all(sql, params): array", "+ one(sql, params): ?array",
                       "+ value(sql, params): mixed"])
B["audit"] = c.class_box(735, y3, "AuditLog", ["(no attributes)"], ["+ write(action, details, userId): void",
                                                                    "+ latest(limit, offset): array", "+ count(): int"])
B["auth"] = c.class_box(1060, y3, "Auth", ["+ ROLE_ADMIN = 'admin'", "+ ROLE_EDITOR = 'editor'"],
                        ["+ attempt(email, password, security): ?string", "+ logout(): void", "+ user(): ?array",
                         "+ isAdmin(): bool", "+ require(adminOnly): array", "+ passwordIsStrong(pw): bool"])
B["http"] = c.class_box(1430, y3, "HttpClient", ["(no attributes)"], ["+ get(url, timeout): string"])

bottom = lambda b: b["y"] + b["h"]
right = lambda b: b["x"] + b["w"]
mid_y = lambda b: b["y"] + b["h"] / 2
dep = dict(kind="open", dashed=True)

# DataImporter uses five classes in row 2 and AuditLog: one trunk, one bus, one drop per class
imp = B["importer"]
trunk_x, bus_y = imp["x"] + imp["w"] / 2, 375
drops = {"stat": 250, "survey": 520, "sheet": 840, "sc": 1150, "wb": 1500}
c.line([(trunk_x, bottom(imp)), (trunk_x, bus_y)], dashed=True)
c.line([(drops["stat"], bus_y), (drops["wb"], bus_y)], dashed=True)
for key, x in drops.items():
    c.arrow([(x, bus_y), (x, B[key]["y"])], **dep)
c.arrow([(970, bus_y), (970, B["audit"]["y"])], **dep)                    # passes between the reader and the client
c.text(trunk_x + 6, bus_y - 18, "«use»", size=8, style="italic", ha="left")

# InsightEngine -> StatRepository
c.arrow([(140, bottom(B["insight"])), (140, B["stat"]["y"])], **dep)
# repositories -> Database
c.arrow([(343, bottom(B["stat"])), (343, B["db"]["y"])], **dep)
c.arrow([(470, bottom(B["survey"])), (470, B["db"]["y"])], **dep)
# Settings, BackupService, AuditLog -> Database
c.arrow([(right(B["settings"]), mid_y(B["settings"])), (B["db"]["x"], mid_y(B["settings"]))], **dep)
c.arrow([(right(B["backup"]), mid_y(B["backup"])), (B["db"]["x"], mid_y(B["backup"]))], **dep)
c.arrow([(B["audit"]["x"], mid_y(B["audit"])), (right(B["db"]), mid_y(B["audit"]))], **dep)
# Auth -> AuditLog, Auth -> Database
c.arrow([(B["auth"]["x"], mid_y(B["audit"])), (right(B["audit"]), mid_y(B["audit"]))], **dep)
ax = B["auth"]["x"] + B["auth"]["w"] / 2
c.arrow([(ax, bottom(B["auth"])), (ax, 1010), (440, 1010), (440, bottom(B["db"]))], **dep)
# API clients -> HttpClient
c.arrow([(1150, bottom(B["sc"])), (1150, 735), (1470, 735), (1470, B["http"]["y"])], **dep)
c.arrow([(1560, bottom(B["wb"])), (1560, B["http"]["y"])], **dep)

c.note(1440, 900, 255, "Dashed arrows show a «use»\ndependency. Lang, Database and Auth\nare also used by every page script.")
c.save(OUT)
