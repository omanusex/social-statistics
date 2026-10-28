from docbuilder import *

S = "screens/"

CH4 = [
    CHAPTER(4),
    H1("Chapter 4: Design"),

    H2("4.1 Introduction"),
    P("This chapter explains how the Social Media Statistics Observatory was designed before and during its development. "
      "It starts with the overall architecture of the system and then moves to the behaviour of its users through a use case "
      "diagram and five use case specifications. The internal structure of the code is shown in a class diagram, and the way "
      "objects talk to each other at run time is shown in three sequence diagrams. Two activity diagrams follow the two "
      "longest processes in the system: importing a data file and answering the questionnaire."),
    P("The second half of the chapter covers the database. An entity relationship diagram shows the eleven tables and the "
      "links between them, and a data dictionary describes every column. The chapter ends with the interface design, the site "
      "map, and the security measures built into each layer. Screenshots of every page, together with the code behind them, "
      "are given in Chapter 5."),

    # ------------------------------------------------------------------ architecture
    H2("4.2 System architecture"),
    P("The system follows a three-tier architecture: a client tier that runs in the web browser, an application tier made of "
      "PHP pages running on Apache, and a data tier made of a MySQL (MariaDB) database and a private storage folder. "
      "Two external services sit beside the application tier and supply the public statistics. Figure 4.1 shows these parts "
      "and the type of data that moves between them."),
    P("As can be seen from Figure 4.1, the browser never talks to the database or to the external services directly. Ordinary "
      "pages are rendered on the server, while the charts ask a small JSON API (`api/series.php`, `api/compare.php` and "
      "`api/map.php`) for their data after the page has loaded. All database work goes through one PDO connection, and only "
      "the application tier contacts StatCounter and the World Bank, using cURL over HTTPS. This keeps the public pages fast "
      "and means that a change in an external source affects only one class."),
    LANDSCAPE(),
    FIG_("fig4_architecture.png", "Three-tier architecture of the Social Media Statistics Observatory", width_cm=25.5, max_h_cm=15.5),
    PORTRAIT(),
    P("The choice of plain PHP 8.2 and MySQL, without a framework, was made for two reasons. The project has to run on XAMPP, "
      "which is installed in the college labs, and the code has to be readable by an examiner who may not know Laravel or "
      "Symfony. To keep the code organised anyway, the logic was separated into fourteen small classes in `includes/classes`, "
      "and the page files only collect input, call these classes and print HTML."),

    # ------------------------------------------------------------------ use cases
    H2("4.3 Use case diagram and specifications"),
    P("The system has four human actors and two external systems. A **visitor** is anyone who opens the website. A **survey "
      "respondent** is a visitor who also answers the questionnaire, so the diagram shows the respondent as a specialisation of "
      "the visitor. A **data editor** signs in to keep the data up to date, and an **administrator** can do everything a data "
      "editor does plus manage accounts, read the audit log and take backups. StatCounter and the World Bank API take part "
      "only in the \"Update data from APIs\" use case. Figure 4.2 shows the complete use case diagram."),
    LANDSCAPE(),
    FIG_("fig4_usecase.png", "Use case diagram", width_cm=24, max_h_cm=16.5),
    PORTRAIT(),
    P("Figure 4.2 also shows two relationships between use cases. Uploading a file always includes a preview step in which "
      "every row is validated, so \"Preview and validate rows\" is linked with «include». Downloading the data as CSV or PNG "
      "is an optional step that a visitor may take while exploring the statistics, so it extends \"Explore statistics\". "
      "Tables 4.1 to 4.5 describe the five most important use cases in detail."),
    TABLE("Use case specification: UC-01 Explore statistics", ["Field", "Description"], [
        ["Use case ID", "UC-01"],
        ["Name", "Explore statistics with filters"],
        ["Actor", "Visitor"],
        ["Description", "The visitor chooses a country, a statistic type, a device group, a period and the platforms to show, then reads the result as a chart and a table."],
        ["Precondition", "The database contains at least one statistic type with data."],
        ["Main flow", "1. The visitor opens the Explore page.\n2. The system shows Oman, social media traffic share, all devices and the last 24 months as defaults.\n3. The visitor changes the filters and presses Apply.\n4. The system loads the series from `api/series.php` and draws the chart and the table.\n5. The visitor reads the values or copies the link."],
        ["Alternative flows", "3a. The visitor picks a yearly statistic (for example internet users): the device filter is hidden and years are shown instead of months.\n4a. No value exists for the selection: the system shows a \"no data\" message instead of an empty chart."],
        ["Extension", "Download data (CSV / PNG): the visitor saves the table as CSV or the chart as an image."],
        ["Postcondition", "The page address contains every filter, so the same view can be shared or opened again."],
    ], widths=[1, 4], size=10),
    TABLE("Use case specification: UC-02 Answer questionnaire", ["Field", "Description"], [
        ["Use case ID", "UC-02"],
        ["Name", "Answer questionnaire"],
        ["Actor", "Survey respondent"],
        ["Description", "A young person answers seven short questions about the platforms they use and how they use them."],
        ["Precondition", "The administrator has opened the questionnaire."],
        ["Main flow", "1. The respondent opens the questionnaire page.\n2. The respondent answers the questions and ticks the consent box.\n3. The system checks the CSRF token, the hidden honeypot field and every answer.\n4. The system saves the answer and the chosen platforms in one transaction.\n5. The respondent is taken to the Local Study page with a thank-you message."],
        ["Alternative flows", "1a. The questionnaire is closed: a message is shown and no form is displayed.\n3a. An answer is missing or the favourite platform is not one of the used platforms: the errors are listed and the answers are kept.\n3b. The respondent already answered in the same session: the answer is refused."],
        ["Postcondition", "The results on the Local Study page include the new answer. No name, e-mail or IP address is stored with it."],
    ], widths=[1, 4], size=10),
    TABLE("Use case specification: UC-03 Log in", ["Field", "Description"], [
        ["Use case ID", "UC-03"],
        ["Name", "Log in"],
        ["Actors", "Data editor, Administrator"],
        ["Description", "A staff member signs in to reach the admin panel."],
        ["Precondition", "The user has an account created by an administrator (or by the installer)."],
        ["Main flow", "1. The user opens `admin/login.php` and enters an e-mail address and password.\n2. The system checks the CSRF token and finds the account.\n3. The password is compared with the stored hash using `password_verify()`.\n4. The system resets the failed attempt counter, renews the session id and writes \"login\" in the audit log.\n5. The dashboard opens."],
        ["Alternative flows", "3a. Wrong password: the counter increases and a general error is shown. After five wrong attempts the account is locked for 15 minutes.\n2a. Unknown e-mail: the same general error is shown, so the page does not reveal which addresses exist."],
        ["Postcondition", "A session is open. It closes after 30 minutes without activity or when the user signs out."],
    ], widths=[1, 4], size=10),
    TABLE("Use case specification: UC-04 Update data from APIs", ["Field", "Description"], [
        ["Use case ID", "UC-04"],
        ["Name", "Update data from APIs"],
        ["Actors", "Data editor (primary); StatCounter and World Bank API (secondary)"],
        ["Description", "The data editor downloads the newest figures from the two public sources."],
        ["Precondition", "The user is signed in and the server has internet access."],
        ["Main flow", "1. The user opens \"Update from APIs\".\n2. For StatCounter, the user chooses countries, device groups and a period, then presses Download now.\n3. The system requests one CSV file per country and device, converts each file into records and saves them with an upsert inside one transaction.\n4. The system records the import, writes the audit log and shows how many values were new, updated or failed.\n5. For the World Bank, the user chooses a start year; the system calls the JSON API for internet users and population."],
        ["Alternative flows", "3a. One request fails (timeout or HTTP error): the other countries continue, and the import is marked \"partial\" with the error message.\n3b. A month in the CSV contains only zeros (a future month): it is skipped."],
        ["Postcondition", "The statistics are up to date and the source shows the date of the last update."],
    ], widths=[1, 4], size=10),
    TABLE("Use case specification: UC-05 Upload Excel / CSV file", ["Field", "Description"], [
        ["Use case ID", "UC-05"],
        ["Name", "Upload Excel / CSV file (includes Preview and validate rows)"],
        ["Actor", "Data editor"],
        ["Description", "The user adds data that is not available through an API: a StatCounter CSV saved by hand, figures copied from a published report, or questionnaire answers."],
        ["Precondition", "The user is signed in. The file follows one of the downloadable templates."],
        ["Main flow", "1. The user chooses the kind of file and the file itself.\n2. The system checks the size (maximum 5 MB), the extension (.csv or .xlsx) and, for Excel files, the ZIP signature.\n3. The file is stored under a random name and every row is validated.\n4. A preview shows the number of valid rows, each error with its row number, and the first ten rows.\n5. The user confirms and the rows are saved in one transaction."],
        ["Alternative flows", "2a. The file is too large or has the wrong type: it is rejected before it is stored.\n5a. The user cancels: the temporary file is deleted and nothing is saved.\n5b. A database error happens: the transaction is rolled back and the import is marked \"failed\"."],
        ["Postcondition", "The import appears in Import history, where it can be undone later."],
    ], widths=[1, 4], size=10),

    # ------------------------------------------------------------------ class diagram
    H2("4.4 Class diagram"),
    P("Figure 4.3 shows the fourteen PHP classes and the dependencies between them. Most classes only have static methods, "
      "because they wrap a single shared resource such as the database connection or the current language. The dashed arrows "
      "mean that one class uses another; there is no inheritance in the design, which keeps each class easy to test and "
      "replace."),
    LANDSCAPE(),
    FIG_("fig4_class.png", "Class diagram of the PHP classes in includes/classes", width_cm=25.5, max_h_cm=16),
    PORTRAIT(),
    P("Two parts of Figure 4.3 deserve attention. First, `DataImporter` is the only class that writes statistics, whatever "
      "their source, so validation and logging happen in one place. Second, the two API clients share `HttpClient`, which means "
      "that timeouts and error handling for outside requests are written once. Table 4.6 summarises the job of each class."),
    TABLE("Responsibilities of the classes", ["Class", "Responsibility"], [
        ["Database", "Opens one PDO connection (UTF-8, exceptions on error, real prepared statements) and offers short helpers: query, all, one, value."],
        ["Auth", "Checks passwords, locks accounts after five failures, renews the session id, enforces the 30-minute idle timeout and the two roles."],
        ["Lang", "Reads the language from `?lang=` or a cookie, loads `lang/en.php` or `lang/ar.php` and returns the text direction (ltr / rtl)."],
        ["Settings", "Reads and writes small key/value settings such as whether the questionnaire is open."],
        ["AuditLog", "Writes security events (sign-in, failed sign-in, import, delete, backup) with the user and IP address."],
        ["StatRepository", "All queries for countries, platforms, statistic types and values; upsert; moving average and correlation."],
        ["SurveyRepository", "Validates and saves questionnaire answers and builds the summaries shown on the Local Study page."],
        ["InsightEngine", "Writes up to five findings in plain language from the newest data, using fixed rules."],
        ["DataImporter", "Parses and validates StatCounter files, template files and questionnaire files; saves records in transactions; undoes imports."],
        ["SpreadsheetReader", "Reads the first sheet of a CSV or XLSX file without any external library (ZipArchive and SimpleXML)."],
        ["StatCounterClient", "Builds the StatCounter CSV request for a country, device and period, and turns the CSV text into months and shares."],
        ["WorldBankClient", "Calls the World Bank v2 API for the internet users and population indicators."],
        ["HttpClient", "Performs a GET request with cURL, with time limits and HTTP error checks."],
        ["BackupService", "Writes every table to a single .sql file that can be restored in phpMyAdmin."],
    ], widths=[1.3, 4], size=10),

    # ------------------------------------------------------------------ sequence
    H2("4.5 Sequence diagrams"),
    P("These sequence diagrams show the interaction between different objects and how they operate with one another over "
      "time. Three scenarios were chosen because together they cover the public site, the data update and the security of "
      "the admin panel."),
    P("Figure 4.4 shows that the Explore page is built in two steps. The first request (messages 2 to 7) returns the page with "
      "the filter lists. The browser then calls `api/series.php` (message 8), which reads the values, fills missing months with "
      "empty values and, when asked, applies the three-month average (message 12a). Splitting the work this way lets the page "
      "appear quickly while the chart data is still loading."),
    LANDSCAPE(),
    FIG_("fig4_seq_explore.png", "Sequence diagram: a visitor explores the statistics", width_cm=21, max_h_cm=16.3),
    FIG_("fig4_seq_sync.png", "Sequence diagram: the administrator updates the data from StatCounter", width_cm=25, max_h_cm=15.5),
    FIG_("fig4_seq_login.png", "Sequence diagram: signing in to the admin panel", width_cm=19, max_h_cm=16.3),
    PORTRAIT(),
    P("In Figure 4.5 the first loop runs once for every country and device. In the test run of this project that meant 17 "
      "countries times 3 device groups, or 51 requests, which took 1 minute 49 seconds and produced 20,475 values. The "
      "second loop shows why repeating an update is safe: the upsert inserts new months and only changes rows whose value "
      "has changed, so running it twice does not create duplicates."),
    P("Figure 4.6 uses an «alt» fragment for the three possible results of a sign-in. The locked case is checked before the "
      "password, so a locked account cannot be unlocked by guessing. In the successful case the session id is renewed "
      "(message 8) to prevent session fixation, and in both the successful and the failed cases a line is written to the "
      "audit log."),

    # ------------------------------------------------------------------ activity
    H2("4.6 Activity diagrams"),
    P("Activity diagrams describe a process step by step, including its decisions. Swimlanes separate what the person does "
      "from what the system does."),
    FIG_("fig4_act_import.png", "Activity diagram: importing an Excel or CSV file", width_cm=14.5, max_h_cm=21),
    P("Figure 4.7 shows the safety checks in the import process. A file is checked twice: once as a file (size, extension, "
      "ZIP signature) and once row by row. Nothing reaches the statistics table until the user has seen the preview and "
      "pressed confirm, and if the database reports an error halfway through, the whole import is rolled back."),
    FIG_("fig4_act_survey.png", "Activity diagram: answering the questionnaire", width_cm=14.5, max_h_cm=21),
    P("Figure 4.8 shows that the questionnaire has four exits besides a successful answer: the questionnaire may be closed, "
      "the hidden field may reveal a bot, the visitor may already have answered, or an answer may be invalid. Only the last "
      "case sends the respondent back to the form, with their earlier answers still selected."),

    # ------------------------------------------------------------------ database
    H2("4.7 Database design"),
    P("The database is called `social_stats` and uses the `utf8mb4` character set so that Arabic names are stored correctly. "
      "Figure 4.9 shows the entity relationship diagram in crow's foot notation."),
    LANDSCAPE(),
    FIG_("fig4_erd.png", "Entity relationship diagram of the social_stats database", width_cm=25.5, max_h_cm=16.3),
    PORTRAIT(),
    P("The central table in Figure 4.9 is `stats`. Each row is one number: one country, one platform, one statistic type, "
      "one device group and one period. Keeping every source in this single shape was the most important design decision, "
      "because it means the same filters, charts and downloads work for StatCounter data, World Bank data and any figures "
      "uploaded from a report. Statistics that belong to a whole country, such as population, leave `platform_id` empty."),
    P("A unique key stops the same value from being stored twice. MySQL treats two empty values as different, so a unique key "
      "on a nullable `platform_id` would not work for country-level statistics. The table therefore has a generated column, "
      "`platform_key`, that turns an empty platform into 0, and the unique key uses that column instead."),
    P("The questionnaire uses two tables: `survey_responses` holds one row per respondent and `survey_response_platforms` "
      "links each respondent to the platforms they use, because one person can use several platforms. The remaining tables "
      "support administration: `users`, `imports`, `audit_log`, `sources` and `settings`. Tables 4.7 to 4.17 describe the "
      "metadata stored for each entity."),
]

DD = [
    ("stats", "One statistic value", [
        ["id", "BIGINT UNSIGNED", "PK, auto increment", "Row number"],
        ["country_id", "INT UNSIGNED", "FK → countries.id", "Country the value belongs to"],
        ["platform_id", "INT UNSIGNED, NULL", "FK → platforms.id", "Platform; empty for country-level statistics"],
        ["metric_id", "INT UNSIGNED", "FK → metrics.id", "Statistic type"],
        ["device", "ENUM('all','desktop','mobile')", "default 'all'", "Device group"],
        ["period", "DATE", "", "First day of the month or year"],
        ["value", "DECIMAL(16,2)", "", "The number itself (percentage or count)"],
        ["source_id", "INT UNSIGNED", "FK → sources.id", "Where the value came from"],
        ["import_id", "INT UNSIGNED, NULL", "FK → imports.id", "Import that added or last changed it (used for undo)"],
        ["updated_at", "DATETIME", "auto", "Last change"],
        ["platform_key", "INT UNSIGNED", "generated, part of UNIQUE", "IFNULL(platform_id, 0) for the unique key"],
    ]),
    ("countries", "Countries and regions", [
        ["id", "INT UNSIGNED", "PK", "Row number"],
        ["iso2", "CHAR(2)", "UNIQUE", "Two-letter code used by StatCounter (WW = worldwide)"],
        ["iso3", "CHAR(3)", "", "Three-letter code used by the World Bank"],
        ["name_en / name_ar", "VARCHAR(80)", "", "English and Arabic names"],
        ["region", "ENUM('GCC','Arab','World')", "", "Group used in menus and averages"],
        ["is_default", "TINYINT(1)", "", "1 for Oman, the country shown first"],
        ["is_active", "TINYINT(1)", "", "Hides a country without deleting its data"],
    ]),
    ("platforms", "Social media platforms", [
        ["id", "INT UNSIGNED", "PK", "Row number"],
        ["slug", "VARCHAR(40)", "UNIQUE", "Short code used in links and templates (e.g. instagram)"],
        ["name", "VARCHAR(60)", "", "Display name"],
        ["statcounter_name", "VARCHAR(60), NULL", "", "Column title in StatCounter files; empty for TikTok, Snapchat and WhatsApp"],
        ["color", "CHAR(7)", "", "Colour used in every chart"],
        ["sort_order", "SMALLINT", "", "Order in lists"],
    ]),
    ("metrics", "Statistic types", [
        ["id", "INT UNSIGNED", "PK", "Row number"],
        ["code", "VARCHAR(40)", "UNIQUE", "traffic_share, internet_users, population, platform_users"],
        ["name_en / name_ar", "VARCHAR(100)", "", "Names shown in the filter"],
        ["unit", "VARCHAR(20)", "", "% or people"],
        ["granularity", "ENUM('monthly','yearly')", "", "Controls the period filter"],
        ["per_platform", "TINYINT(1)", "", "0 when the value belongs to the whole country"],
        ["description_en / _ar", "TEXT", "", "Definition shown under the chart and on the About page"],
    ]),
    ("sources", "External data sources", [
        ["id", "INT UNSIGNED", "PK", "Row number"],
        ["code", "VARCHAR(30)", "UNIQUE", "statcounter, worldbank, survey, manual"],
        ["name / url", "VARCHAR", "", "Name and web address for the credits"],
        ["license", "VARCHAR(100)", "", "Licence of the data (CC BY-SA 3.0, CC BY 4.0)"],
        ["last_synced_at", "DATETIME, NULL", "", "Date of the last successful update"],
    ]),
    ("imports", "One file upload or API update", [
        ["id", "INT UNSIGNED", "PK", "Import number shown to the user"],
        ["user_id", "INT UNSIGNED, NULL", "FK → users.id", "Who ran it (kept as NULL if the user is deleted)"],
        ["source_id", "INT UNSIGNED", "FK → sources.id", "Source of the data"],
        ["type", "ENUM('file','api')", "", "Upload or download"],
        ["filename", "VARCHAR(255), NULL", "", "Original file name"],
        ["rows_inserted / rows_updated / rows_failed", "INT UNSIGNED", "", "Counts shown in Import history"],
        ["status", "ENUM('success','partial','failed')", "", "Result"],
        ["message", "TEXT, NULL", "", "Error messages, if any"],
        ["created_at", "DATETIME", "default now", "When it ran"],
    ]),
    ("survey_responses", "One questionnaire answer", [
        ["id", "INT UNSIGNED", "PK", "Row number"],
        ["age_group", "ENUM('13-15','16-18','19-21','22-25')", "", "Age group"],
        ["gender", "ENUM('male','female')", "", "Gender"],
        ["favourite_id", "INT UNSIGNED, NULL", "FK → platforms.id", "Favourite platform"],
        ["main_purpose", "ENUM(education, entertainment, communication, news)", "", "Main reason for use"],
        ["daily_hours", "ENUM('<1','1-2','3-4','5+')", "", "Hours per day"],
        ["late_night_use", "TINYINT(1)", "", "Uses social media after midnight on school nights"],
        ["origin", "ENUM('import','online')", "", "Imported from a file or sent through the website"],
        ["import_id", "INT UNSIGNED, NULL", "FK → imports.id", "Import that added it"],
        ["created_at", "DATETIME", "default now", "Time of the answer"],
    ]),
    ("survey_response_platforms", "Platforms used by each respondent (many-to-many)", [
        ["response_id", "INT UNSIGNED", "PK, FK → survey_responses.id", "The answer (deleted with it)"],
        ["platform_id", "INT UNSIGNED", "PK, FK → platforms.id", "A platform the respondent uses"],
    ]),
    ("users", "Staff accounts", [
        ["id", "INT UNSIGNED", "PK", "Row number"],
        ["name", "VARCHAR(100)", "", "Display name"],
        ["email", "VARCHAR(150)", "UNIQUE", "Sign-in name"],
        ["password_hash", "VARCHAR(255)", "", "bcrypt hash from password_hash(); the password itself is never stored"],
        ["role", "ENUM('admin','editor')", "", "Administrator or data editor"],
        ["failed_logins", "TINYINT UNSIGNED", "", "Wrong passwords in a row"],
        ["locked_until", "DATETIME, NULL", "", "End of the 15-minute lock"],
        ["last_login_at / created_at", "DATETIME", "", "Activity dates"],
    ]),
    ("audit_log", "Security events", [
        ["id", "BIGINT UNSIGNED", "PK", "Row number"],
        ["user_id", "INT UNSIGNED, NULL", "FK → users.id", "Who did it"],
        ["action", "VARCHAR(50)", "INDEX on created_at", "login, login_failed, import, delete_values, backup, ..."],
        ["details", "VARCHAR(500)", "", "Short description"],
        ["ip_address", "VARCHAR(45)", "", "IPv4 or IPv6 address of the request"],
        ["created_at", "DATETIME", "default now", "Time of the event"],
    ]),
    ("settings", "Site settings", [
        ["name", "VARCHAR(50)", "PK", "survey_open, survey_is_demo"],
        ["value", "VARCHAR(255)", "", "Setting value"],
    ]),
]
for name, title, rows in DD:
    CH4.append(TABLE(f"Data dictionary: {name} ({title})", ["Column", "Data type", "Key / constraint", "Description"], rows,
                     widths=[1.5, 1.7, 1.4, 2.8], size=9))

CH4 += [
    # ------------------------------------------------------------------ interface
    H2("4.8 Interface design"),
    P("The interface was designed around a few simple rules. The pages use a classic layout: a dark blue menu bar, white cards "
      "on a light grey background, and one main colour for buttons, so the charts carry most of the colour. Each platform keeps "
      "the same colour in every chart. Text and background colours were chosen for strong contrast, which was a weak point of "
      "the first prototype that used light grey text on a pink background."),
    P("Figure 4.10 shows the site map. The public part needs no account and has five main pages; the admin part is reached "
      "through the sign-in page and has nine pages, two of which are limited to administrators."),
    LANDSCAPE(),
    FIG_("fig4_sitemap.png", "Site map of the public site and the admin panel", width_cm=25, max_h_cm=15),
    PORTRAIT(),
    P("The whole interface exists in English and Arabic. Switching the language does more than translate the words: the "
      "page direction changes to right-to-left, the right-to-left build of Bootstrap is loaded, the Arabic font (Cairo) is used, "
      "and month names and chart legends follow the language. Figures 4.11 and 4.12 show the same page in both languages."),
    FIG_(S + "s01_home.png", "Home page in English (left-to-right layout)", width_cm=14.5, max_h_cm=19),
    FIG_(S + "s02_home_ar.png", "Home page in Arabic (right-to-left layout)", width_cm=14.5, max_h_cm=19),
    P("The layout is responsive. On a phone the menu folds into a button, the cards stack in one column and the charts keep "
      "their height, as Figures 4.13 and 4.14 show. Screenshots of every page are presented with their code in Section 5.3."),
    FIG_(S + "s09_home_mobile.png", "Home page on a phone screen (390 pixels wide) in English", width_cm=6.5, max_h_cm=13),
    FIG_(S + "s10_home_mobile_ar.png", "Home page on a phone screen in Arabic", width_cm=6.5, max_h_cm=13),

    # ------------------------------------------------------------------ security
    H2("4.9 Security measures"),
    P("Security was planned for every layer rather than added at the end. The measures follow the OWASP Top 10 list of the "
      "most common web application risks (OWASP Foundation, 2021). Table 4.18 lists each risk, the measure taken and the file "
      "where it is implemented."),
    TABLE("Security measures", ["Risk", "Measure", "Where"], [
        ["Unauthorised access (access control)", "Every admin page calls `Auth::require()`. Two roles: data editors cannot open the audit log, backups or account management (HTTP 403).", "Auth.php, admin/*.php"],
        ["Stolen or guessed passwords", "Passwords stored as bcrypt hashes (`password_hash`), minimum 8 characters with a letter and a digit, account locked for 15 minutes after 5 wrong attempts, same error message for unknown e-mail and wrong password.", "Auth.php, users.php"],
        ["Session hijacking and fixation", "Session id renewed after sign-in, HttpOnly and SameSite=Lax cookie, automatic sign-out after 30 minutes without activity, sign-out only by POST.", "bootstrap.php, Auth.php"],
        ["Cross-site request forgery (CSRF)", "A random 64-character token in every form, compared with `hash_equals()`; requests without it get HTTP 403.", "helpers.php"],
        ["SQL injection", "All queries use PDO prepared statements with real (not emulated) parameters.", "Database.php"],
        ["Cross-site scripting (XSS)", "All output passes through `htmlspecialchars()`; data printed inside scripts uses JSON with hex-escaped tags and quotes; period values from the address bar must match a date pattern.", "helpers.php, footer.php"],
        ["Dangerous file uploads", "Only .csv and .xlsx up to 5 MB; Excel files must start with the ZIP signature; files saved under a random name outside the public folders and deleted after use.", "admin/import.php"],
        ["Direct access to private files", "`includes/`, `database/`, `lang/` and `storage/` are blocked by .htaccess (HTTP 403).", ".htaccess files"],
        ["Clickjacking and content sniffing", "Headers X-Frame-Options: SAMEORIGIN, X-Content-Type-Options: nosniff and Referrer-Policy: same-origin.", "bootstrap.php"],
        ["Spam in the questionnaire", "Hidden honeypot field, consent box, one answer per session.", "survey.php"],
        ["Unnoticed misuse (database auditing)", "Sign-ins, failed sign-ins, imports, deletions, exports and backups are written to `audit_log` with user and IP address.", "AuditLog.php"],
        ["Data loss (backup and recovery)", "Full .sql backup downloadable by administrators, with a copy kept in `storage/backups`; every import can be undone; imports run inside transactions.", "BackupService.php, DataImporter.php"],
        ["Privacy of respondents", "No names, e-mails or IP addresses stored with questionnaire answers.", "survey_responses table"],
    ], widths=[1.4, 3.6, 1.4], size=9),

    H2("4.10 Summary"),
    P("This chapter described the design of the system from the outside in. The three-tier architecture separates the browser, "
      "the PHP application and the database. The use case diagram and its specifications define what each type of user can "
      "do, and the class, sequence and activity diagrams show how the code carries this out. The database design keeps all "
      "statistics in one table with one shape, which is what makes filtering across sources possible. Finally, the interface "
      "and security sections explain how the system looks and how it protects its data. Chapter 5 shows how this design was "
      "built and tested."),
]
