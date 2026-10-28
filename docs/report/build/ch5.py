import json
import os
from docbuilder import *

S = "screens/"
R = json.load(open(os.path.join(os.path.dirname(__file__), "..", "test_results.json")))
cases = R["cases"]
passed = sum(c["result"] == "Pass" for c in cases)

CH5 = [
    CHAPTER(5),
    H1("Chapter 5: Development and Evaluation"),

    H2("5.1 Introduction"),
    P("This chapter includes the development of the system and its evaluation. It first lists the tools that were used. "
      "Section 5.3 then goes through every page of the website and the admin panel: each page has a short description, a "
      "screenshot and the part of the code that does the main work. Section 5.4 explains how the system was tested and "
      f"reports the results of {len(cases)} test cases, the response times and the defects that testing found. Section 5.5 "
      "describes what is needed to deploy and use the system, and Section 5.6 evaluates the finished system against its "
      "requirements."),
    P("The complete source code is stored in a GitHub repository. Only short extracts are printed here; the line numbers in "
      "each listing refer to the files in that repository."),
    NOTE("[Add the GitHub repository link here and in Section 6.6 after the code is uploaded.]"),

    H2("5.2 Development environment and tools"),
    TABLE("Development tools and libraries", ["Tool", "Version", "Used for"], [
        ["XAMPP (Apache, PHP, MariaDB)", "Apache 2.4, PHP 8.2.12, MariaDB 10.4.32", "Local web server, server-side code and database"],
        ["PHP extensions", "pdo_mysql, curl, zip, simplexml, mbstring", "Database access, API requests, reading Excel files"],
        ["Bootstrap", "5.3.3 (LTR and RTL builds)", "Layout, forms, responsive grid, right-to-left support"],
        ["Bootstrap Icons", "1.11.3", "Icons in menus and buttons"],
        ["Chart.js", "4.4.1", "Line, bar, doughnut and scatter charts"],
        ["jsVectorMap", "1.5.3", "World map of the leading platform"],
        ["phpMyAdmin", "bundled with XAMPP", "Checking tables and restoring backups"],
        ["Visual Studio Code", "-", "Code editor"],
        ["Google Chrome / Microsoft Edge", "156", "Testing and screenshots"],
        ["Python 3.12 with Playwright", "-", "Automated test script and full-page screenshots"],
        ["Git and GitHub", "-", "Version control and code submission"],
    ], widths=[2, 2.2, 3], size=10),
    P("All front-end libraries are stored inside the project in `assets/vendor`, so the website works without internet "
      "access during a presentation. Only the Google fonts and the two data sources need a connection; if the fonts are not "
      "available the browser uses a system font."),

    # -------------------------------------------------------------------- pages
    H2("5.3 Description of the system"),
    P("The website has six public pages and ten admin pages. The public pages are available in English and Arabic; the "
      "screenshots below use English unless stated otherwise."),

    H3("5.3.1 Home page"),
    P("The home page gives a quick picture of one country, Oman by default. Four cards show the leading platform, internet "
      "users, population and the number of questionnaire answers. Below them are the platform shares for the latest month, "
      "the trend of the four leading platforms over twelve months, a map that colours each country by its leading platform, "
      "and a list of findings that the system writes from the data (Figure 5.1)."),
    FIG_(S + "s01_home.png", "Home page", width_cm=15, max_h_cm=19),
    CODE("Loading the data for the home page", "index.php", r"^\$country = StatRepository::countryFromRequest", r"^\$insights ="),
    P("Listing 5.1 shows that the page only asks the repository classes for data. The written findings come from "
      "`InsightEngine`; Listing 5.2 shows one of its rules, which compares the average of the last three months with the "
      "same three months half a year earlier and reports the platform with the largest change."),
    CODE("Rule that finds the biggest change over six months", "includes/classes/InsightEngine.php", r"private function trend", r"^    }$"),

    H3("5.3.2 Explore page"),
    P("The Explore page is the main tool of the website. A visitor chooses a country, a statistic type, a device group, the "
      "start and end month and the platforms, and can switch on a three-month average to smooth out sudden jumps. The chart, "
      "the data table and the CSV download always show the same data, and the address of the page contains all the filters "
      "so that it can be shared (Figure 5.2). Yearly statistics from the World Bank use years instead of months (Figure 5.3)."),
    FIG_(S + "s03_explore.png", "Explore page: monthly traffic share in Oman", width_cm=15, max_h_cm=19),
    FIG_(S + "s04_explore_wb.png", "Explore page: yearly internet users in Oman from the World Bank", width_cm=15, max_h_cm=17),
    CODE("Building the chart series in the JSON API", "api/series.php", r"^\$rows = StatRepository::series", r"^json_response\(\$payload\);"),
    CODE("Three-month moving average", "includes/classes/StatRepository.php", r"public static function movingAverage", r"^    }$"),

    H3("5.3.3 Compare page"),
    P("The Compare page answers the question \"how does Oman compare?\". It draws one platform in up to six countries on the "
      "same chart, ranks all seventeen countries for the chosen month with the Gulf and Arab averages, and plots internet use "
      "against the platform's share for every country. The Pearson correlation coefficient of that plot is calculated and "
      "explained in words (Figure 5.4)."),
    FIG_(S + "s05_compare.png", "Compare page with the ranking table and the scatter chart", width_cm=15, max_h_cm=20),
    CODE("Pearson correlation coefficient", "includes/classes/StatRepository.php", r"public static function correlation", r"^    }$"),
    CODE("Explaining the correlation in plain words", "compare.php", r"^function correlation_text", r"^}$"),

    H3("5.3.4 Local study page"),
    P("This page shows the results of the questionnaire: how many respondents use each platform, their favourite platform, "
      "the main purpose, hours per day and age groups. The results can be filtered by age group and gender (Figure 5.5). The "
      "local study adds TikTok, Snapchat and WhatsApp, which do not appear in StatCounter data."),
    FIG_(S + "s06_study.png", "Local study page", width_cm=15, max_h_cm=19),
    CODE("Summary of the questionnaire answers", "includes/classes/SurveyRepository.php", r"public static function summary", r"^    }$"),

    H3("5.3.5 Questionnaire page"),
    P("The questionnaire has seven questions and a consent box (Figure 5.6). Answers are anonymous. The page rejects answers "
      "without consent, answers where the favourite platform is not one of the platforms used, and a second answer from the "
      "same browser session. A hidden field that people cannot see catches simple spam robots."),
    FIG_(S + "s07_survey.png", "Questionnaire page", width_cm=13, max_h_cm=20),
    CODE("Handling a submitted questionnaire", "survey.php", r"^if \(\$isOpen && \$_SERVER", r"^}$"),

    H3("5.3.6 About page"),
    P("The About page lists the data sources with their licences and the date of the last update, defines every statistic "
      "type, explains the method and states the limits of the data, so that visitors do not read more into the numbers than "
      "they show (Figure 5.7)."),
    FIG_(S + "s08_about.png", "About page", width_cm=14, max_h_cm=20),
    CODE("Choosing the language of the interface", "includes/classes/Lang.php", r"public static function init", r"^    }$"),

    H3("5.3.7 Admin sign-in page"),
    P("Staff members sign in with an e-mail address and password (Figure 5.8). The page gives the same message for an unknown "
      "address and for a wrong password, and locks the account for fifteen minutes after five wrong attempts."),
    FIG_(S + "a01_login.png", "Admin sign-in page", width_cm=13, max_h_cm=12),
    CODE("Checking a sign-in attempt", "includes/classes/Auth.php", r"public static function attempt", r"^    }$"),

    H3("5.3.8 Admin dashboard"),
    P("After signing in, the dashboard shows the number of stored values, countries, the newest month in the database and the "
      "number of questionnaire answers. It also shows when each source was last updated, the latest imports and recent "
      "activity, and warns when the newest month is more than 45 days old (Figure 5.9)."),
    FIG_(S + "a02_dashboard.png", "Admin dashboard", width_cm=15, max_h_cm=19),
    CODE("Data shown on the dashboard", "admin/index.php", r"^\$totals = StatRepository::totals", r"^\$staleDays"),

    H3("5.3.9 Update from APIs page"),
    P("This page downloads the newest data. For StatCounter the user chooses the countries, the device groups and the period; "
      "for the World Bank the user chooses a start year (Figure 5.10). The result shows how many values were new, updated or "
      "failed."),
    FIG_(S + "a03_sync.png", "Update from APIs page", width_cm=15, max_h_cm=17),
    CODE("Reading a StatCounter CSV file", "includes/classes/StatCounterClient.php", r"public static function parse", r"^    }$"),
    CODE("Downloading StatCounter data for many countries", "includes/classes/DataImporter.php", r"public function syncStatCounter", r"^    }$"),

    H3("5.3.10 Upload file page"),
    P("Data editors can upload three kinds of file: a StatCounter CSV, the statistics template, or questionnaire answers. The "
      "file is checked, every row is validated, and a preview lists the valid rows and each error with its row number before "
      "anything is saved (Figures 5.11 and 5.12)."),
    FIG_(S + "a04_import.png", "Upload file page, step 1", width_cm=15, max_h_cm=15),
    FIG_(S + "a05_import_preview.png", "Upload file page, step 2: preview of an Excel file with two invalid rows", width_cm=15, max_h_cm=13),
    CODE("Checking an uploaded file before it is stored", "admin/import.php", r"\$error = match \(true\)", r"^\s*\};\s*$"),
    CODE("Validating each row of the statistics template", "includes/classes/DataImporter.php", r"foreach \(\$sheet\['rows'\] as \$i => \$row\) \{", r"return \['records' => \$records, 'errors' => \$errors\];"),

    H3("5.3.11 Import history page"),
    P("Every upload and every API update is listed with its source, file name, counts, status and user. An import can be "
      "undone, which deletes the values it added or changed (Figure 5.13)."),
    FIG_(S + "a06_imports.png", "Import history page", width_cm=15, max_h_cm=14),
    CODE("Undoing an import", "includes/classes/DataImporter.php", r"public static function rollback", r"^    }$"),

    H3("5.3.12 Stored data page"),
    P("The stored values can be filtered by country, statistic type, platform, device and period, downloaded as CSV, or "
      "deleted one by one or as a filtered group after a confirmation (Figure 5.14)."),
    FIG_(S + "a07_data.png", "Stored data page", width_cm=15, max_h_cm=20),
    CODE("Building the filter safely with placeholders", "admin/data.php", r"^\$where = \['1 = 1'\];", r"^\$whereSql"),

    H3("5.3.13 Questionnaire management page"),
    P("The administrator can open or close the questionnaire, download all answers as CSV, import answers from a file, or "
      "delete all answers after a confirmation (Figure 5.15)."),
    FIG_(S + "a08_survey.png", "Questionnaire management page", width_cm=15, max_h_cm=12),
    CODE("Exporting the answers as CSV", "admin/survey.php", r"^if \(input\('export'\) === 'csv'\)", r"^}$"),

    H3("5.3.14 Accounts page"),
    P("Every user can change their own password here. Administrators also see the list of accounts and can add a data editor "
      "or another administrator, or delete an account other than their own (Figure 5.16)."),
    FIG_(S + "a09_users.png", "Accounts page", width_cm=15, max_h_cm=14),
    CODE("Changing a password", "admin/users.php", r"if \(\$action === 'password'\)", r"^    }$"),

    H3("5.3.15 Audit log page"),
    P("Only administrators can open the audit log. It lists sign-ins, failed sign-ins, imports, deletions, exports and "
      "backups with the user and IP address; failed sign-ins are highlighted (Figure 5.17)."),
    FIG_(S + "a10_log.png", "Audit log page", width_cm=15, max_h_cm=19),
    CODE("Writing an audit log entry", "includes/classes/AuditLog.php", r"public static function write", r"^    }$"),

    H3("5.3.16 Backup page"),
    P("Administrators can download the whole database as a single .sql file. A copy is kept in a private folder on the "
      "server, and the page explains how to restore a backup in phpMyAdmin (Figure 5.18)."),
    FIG_(S + "a11_backup.png", "Backup page", width_cm=15, max_h_cm=12),
    CODE("Creating the backup file", "includes/classes/BackupService.php", r"public static function dump", r"^    }$"),

    H3("5.3.17 Database creation"),
    P("The tables are created by `install.php` from `database/schema.sql`. Listing 5.22 shows the main table with its keys."),
    CODE("The stats table", "database/schema.sql", r"^CREATE TABLE stats", r"^\) ENGINE"),

    # -------------------------------------------------------------------- testing
    H2("5.4 Testing and evaluation"),
    H3("5.4.1 Importance of testing"),
    P("Testing shows whether the system does what its requirements say and whether it fails safely when something "
      "unexpected happens. This matters more than usual in this project for two reasons. The numbers on the website are meant "
      "to be quoted by students, teachers and others, so an error in an import or a calculation would spread to their work. "
      "And the admin panel accepts files and form data from outside, which is where most web security problems start. "
      "Sommerville (2016) describes testing as a way to show that a program does what it is intended to do and to discover "
      "defects before it is put into use; both aims applied here."),

    H3("5.4.2 Test strategy"),
    P("The tests are black-box tests that use the system the way a visitor or administrator would: through web requests and "
      "the browser. They were grouped into five kinds:"),
    BUL([
        "Functional tests check each feature against its use case: filters, the moving average, comparisons, the map, the "
        "questionnaire, imports, undo, backups and the World Bank update.",
        "Validation tests send wrong input on purpose: an unknown statistic type, an invalid device, an unknown country, "
        "a text file instead of a spreadsheet, and an Excel file with bad rows.",
        "Security tests try the attacks listed in Table 4.18: pages without signing in, wrong passwords, forms without a "
        "CSRF token, a script in the address bar, a data editor opening an administrator page, and requests for private files.",
        "Performance tests measure the response time of the main pages and API calls ten times each.",
        "Compatibility tests open every public page in two browsers at desktop and phone width and check for errors, "
        "missing charts and sideways scrolling.",
    ]),
    P("To make the tests repeatable, they were written as a Python script (`docs/report/build/tests.py`) that sends real "
      "requests to the running website and checks the database before and after each test. The script creates two temporary "
      "accounts for the lock-out and role tests and deletes them, and removes any test answers it adds. The screenshots and "
      "the browser checks were also automated with Playwright, while the layout of each page was checked by eye."),
    TABLE("Test schedule", ["Stage", "Scope", "Test cases", "Method", "Date"], [
        ["1", "Public pages and JSON API", "TC01–TC12", "Automated requests", "28/10/2026"],
        ["2", "Questionnaire", "TC13–TC17", "Automated form submissions", "28/10/2026"],
        ["3", "Admin panel and security", "TC18–TC24, TC29", "Automated requests, temporary accounts", "28/10/2026"],
        ["4", "Imports, undo, backup, API update", "TC25–TC28, TC30", "Automated requests and database checks", "28/10/2026"],
        ["5", "Performance", "7 pages × 10 requests", "Timed requests", "28/10/2026"],
        ["6", "Compatibility and layout", "7 pages × 2 browsers × 2 widths", "Playwright and visual check", "28/10/2026"],
    ], widths=[0.6, 2.2, 1.6, 2.4, 1.2], size=10),

    H3("5.4.3 Test cases and results"),
    P(f"Table 5.3 lists the test cases with the expected result, the actual result recorded by the test script, the status "
      f"and the time taken. All tests were run by the developer on {R['started'][:10].split('-')[2]}/"
      f"{R['started'][5:7]}/{R['started'][:4]} on the local XAMPP server. {passed} of {len(cases)} test cases passed."),
    TABLE("Test cases and results", ["ID", "Area", "Test case", "Expected result", "Actual result", "Status", "Time (ms)"],
          [[c["id"], c["area"], c["title"], c["expected"], c["actual"], c["result"], str(c["ms"])] for c in cases],
          widths=[0.55, 0.9, 1.6, 2.0, 2.2, 0.6, 0.6], size=8),

    H3("5.4.4 Performance results"),
    P("Each page and API call was requested ten times from a fresh session. Table 5.4 shows the times measured on a laptop "
      "running XAMPP, with the database holding about 21,300 values."),
    TABLE("Response times (10 requests each)", ["Page or request", "Average (ms)", "Fastest (ms)", "Slowest (ms)"],
          [[p["page"], str(p["avg"]), str(p["min"]), str(p["max"])] for p in R["perf"]], widths=[3, 1, 1, 1], size=10),
    P("Every page answered in under half a second on average, well inside the one-second target. The Compare page is the "
      "slowest because it reads the latest internet-use value for each of the seventeen countries before drawing the scatter "
      "chart. The full StatCounter update is much slower, 1 minute 49 seconds for 51 downloads, but it runs only when an "
      "administrator asks for it and it shows a progress message while it works."),

    H3("5.4.5 Compatibility results"),
    TABLE("Browser and screen tests", ["Browser", "Screen width", "Pages", "Charts drawn", "Console errors", "Sideways scrolling", "Status"], [
        ["Google Chrome 156", "1366 px", "7", "12", "0", "None", "Pass"],
        ["Google Chrome 156", "390 px (phone)", "7", "12", "0", "None", "Pass"],
        ["Microsoft Edge 156", "1366 px", "7", "12", "0", "None", "Pass"],
        ["Microsoft Edge 156", "390 px (phone)", "7", "12", "0", "None", "Pass"],
        ["Mozilla Firefox, Safari", "-", "-", "-", "-", "-", "Not tested"],
    ], widths=[1.6, 1.3, 0.6, 0.9, 0.9, 1.1, 0.9], size=9),
    P("Firefox and Safari were not available on the test machine. The site uses only standard HTML, CSS and JavaScript "
      "features supported by both, but this was not confirmed by a test."),

    H3("5.4.6 Defects found and fixed"),
    P("Testing found several defects during development. Table 5.6 lists them with their cause and the fix. All of them were "
      "fixed and the affected tests were run again."),
    TABLE("Defects found during testing", ["No.", "Defect", "Cause", "Fix"], [
        ["D1", "The chart on the Explore page stayed empty although the API returned 24 months of data.", "The chart-type list and the chart canvas both had the id \"chart\", so the script found the list instead of the canvas. The browser console showed \"can't acquire context\".", "The canvas was renamed `mainChart` on the Explore and Compare pages."],
        ["D2", "In Arabic, age groups appeared reversed (\"15–13\" instead of 13–15).", "The browser's bidirectional text rules reorder numbers joined by a dash inside right-to-left text.", "The Arabic labels were written as words: \"من 13 إلى 15\"."],
        ["D3", "A form sent without its CSRF token produced a server error (HTTP 500).", "The code answered with status 419, which Apache does not recognise.", "The status was changed to 403 Forbidden."],
        ["D4", "The population card on the home page said \"of population\".", "It reused the text of the internet users card.", "A separate text, \"people · World Bank {year}\", was added in both languages."],
        ["D5", "Test TC11 failed: text from the address bar was printed inside a script block on the Explore page.", "The start and end period were copied from the address without checking their format. JSON escaping stopped the script from running, but the value still reached the page.", "Periods must now match YYYY-MM or YYYY, and all JSON printed in pages escapes <, >, & and quotes."],
        ["D6", "Arabic menu items wrapped onto two lines; the admin menu wrapped at 1000 px; the footer floated in the middle of short pages.", "Long labels and fixed column widths.", "Shorter Arabic labels, no wrapping in the menu, a wider admin menu column and a footer that stays at the bottom."],
    ], widths=[0.4, 2, 2.4, 2.2], size=9),
    P("One more problem came from the environment rather than the code. During the backup test, which creates and drops a "
      "complete copy of the database, the MariaDB server of the XAMPP installation stopped several times with the message "
      "\"MySQL server has gone away\". Its error log showed InnoDB warnings about pages with a \"log sequence number in the "
      "future\" going back to September 2026, long before this project started, so the server's own files were already "
      "damaged. The project tables were checked with `CHECK TABLE` after the restart and were all reported as OK. As a "
      "precaution, the automated backup test was changed to compare the backup file with the live tables instead of creating "
      "a second database; a full restore into a separate database had already been done once and gave the same row counts."),

    H3("5.4.7 Analysis of the test results"),
    P(f"All {len(cases)} test cases passed in the final run, and every page responded in under 0.4 seconds on average. The "
      "functional tests confirm that the filters, calculations and imports produce the expected numbers; for example, the "
      "smoothed value for June 2025 was exactly the mean of April, May and June. The security tests show that the admin "
      "panel cannot be reached without signing in, that roles are enforced, and that common attacks are refused. The defects "
      "in Table 5.6 also show why the tests were worth writing: D5 was a real weakness that a manual look at the page would not "
      "have revealed."),
    P("The tests have limits. They were run by the developer, on one machine, against a local server, so they say nothing "
      "about behaviour under many simultaneous users or on a hosted server. No usability test with real users was carried "
      "out, so the ease of use of the admin panel for a non-technical data editor has not been measured."),

    # -------------------------------------------------------------------- deployment
    H2("5.5 Deployment"),
    H3("5.5.1 Server requirements"),
    TABLE("Server specification", ["Item", "Minimum", "Used in this project"], [
        ["Web server", "Apache 2.4 with .htaccess support", "Apache 2.4 (XAMPP 8.2)"],
        ["PHP", "8.1 or later with pdo_mysql, curl, zip, simplexml, mbstring", "PHP 8.2.12"],
        ["Database", "MySQL 5.7+ or MariaDB 10.3+ (generated columns)", "MariaDB 10.4.32"],
        ["Disk space", "50 MB for the code, about 10 MB per year of data", "about 12 MB including libraries"],
        ["Memory", "2 GB RAM", "8 GB laptop"],
        ["Network", "Internet access only for data updates", "Home broadband"],
        ["Hosting (optional)", "Any shared PHP host with HTTPS", "Not deployed online yet"],
    ], widths=[1.2, 2.6, 1.8], size=10),
    H3("5.5.2 Client requirements"),
    TABLE("Client specification", ["Item", "Requirement"], [
        ["Device", "Desktop, laptop, tablet or phone"],
        ["Screen", "360 pixels wide or more"],
        ["Browser", "A current version of Chrome, Edge, Firefox or Safari with JavaScript enabled"],
        ["Network", "Access to the server; internet access is only needed for the web fonts"],
        ["Language", "English or Arabic"],
    ], widths=[1, 4], size=10),
    H3("5.5.3 Installation steps"),
    NUM([
        "Copy the project folder to `C:\\xampp\\htdocs\\social-statistics`.",
        "Start Apache and MySQL in the XAMPP Control Panel.",
        "If the MySQL user is not `root` without a password, edit `includes/config.php`.",
        "Open `http://localhost/social-statistics/`. The installer opens automatically; enter the administrator's name, "
        "e-mail and password, and choose whether to load demonstration questionnaire answers.",
        "Sign in at `/admin`, open \"Update from APIs\" and download the StatCounter and World Bank data.",
        "Import the real questionnaire answers from \"Upload file\" and set `debug` to false in `includes/config.php` before "
        "the site is used by others.",
    ]),
    H3("5.5.4 User skills and training"),
    TABLE("Skills and training for each user group", ["User group", "Skills needed", "Training", "Time"], [
        ["Visitor (students, parents, teachers, researchers)", "Basic web browsing", "None; the About page explains the data and each chart has a short description", "-"],
        ["Survey respondent", "Basic web browsing", "None", "2 minutes to answer"],
        ["Data editor", "Basic Excel; understanding of the statistic types", "Demonstration of updating from the APIs, filling the templates, reading the preview and undoing an import", "1 hour"],
        ["Administrator", "As data editor, plus XAMPP and phpMyAdmin basics", "Installation, accounts and roles, audit log, backup and restore", "2 hours"],
    ], widths=[1.6, 1.6, 2.6, 0.8], size=10),

    # -------------------------------------------------------------------- evaluation
    H2("5.6 Critical evaluation"),
    P("The first prototype of this idea was a static page with hard-coded numbers from fifty questionnaire answers and a "
      "login button that only displayed a message. The final system turns that idea into a working data platform: the numbers "
      "come from public sources and from files, they are stored in a database, and they can be filtered, compared and "
      "downloaded in two languages."),
    P("The design met its main specification. Visitors can filter by country and by type of statistic, which was the core of "
      "the original idea, and they can also filter by device and period. Oman is the starting point on every page, while "
      "sixteen other countries and regions make comparison possible. The data arrives through two routes, an API (World Bank) "
      "and downloadable files (StatCounter and Excel templates), which covers the requirement that the statistics must come "
      "from an outside source rather than be typed into the code."),
    P("Some parts are weaker. StatCounter measures the traffic that platforms send to websites, not how many people use them, "
      "so it underrates messaging and short-video apps. The local study covers that gap, but the answers stored now are "
      "demonstration data generated from the summary figures of the pilot questionnaire, and they must be replaced by the "
      "real answers. The StatCounter download also relies on the same CSV address that the StatCounter website uses for its "
      "own download button; it is public but not an official API, so it may change without notice. Table 5.10 checks each "
      "requirement."),
    TABLE("Requirements checklist", ["ID", "Requirement", "Status", "Evidence"], [
        ["FR1", "Show social media statistics for Oman by default", "Met", "Home, Explore, Compare (Figures 5.1–5.4)"],
        ["FR2", "Allow other countries to be viewed", "Met", "17 countries and regions; TC10"],
        ["FR3", "Filter by type of statistic", "Met", "4 statistic types; types without data are hidden; TC05"],
        ["FR4", "Filter by device and period", "Met", "TC03, TC04"],
        ["FR5", "Show results as charts and tables", "Met", "Line, bar, doughnut, scatter charts, map, tables"],
        ["FR6", "Download data and share a view", "Met", "CSV, PNG, copy link"],
        ["FR7", "Compare Oman with other countries", "Met", "Ranking with Gulf and Arab averages, correlation; TC06"],
        ["FR8", "Get statistics from an API", "Met", "World Bank API, StatCounter CSV; TC30"],
        ["FR9", "Import statistics from Excel or CSV files", "Met", "Three file types with preview; TC24–TC26"],
        ["FR10", "Present a local study from the questionnaire", "Partly met", "Works and is tested (TC12–TC17), but real answers still need to be imported"],
        ["FR11", "Write findings automatically", "Met", "Five rules in InsightEngine (Figure 5.1)"],
        ["FR12", "Protect the admin panel with accounts and roles", "Met", "TC18–TC23"],
        ["FR13", "Keep a history of imports and allow undo", "Met", "TC26"],
        ["FR14", "Back up the database", "Met", "TC28"],
        ["NFR1", "English and Arabic with right-to-left layout", "Met", "TC02; Figures 4.11–4.14"],
        ["NFR2", "Usable on phones", "Met", "390 px tests, no sideways scrolling (Table 5.5)"],
        ["NFR3", "Pages load in under one second", "Met", "Averages 0.26–0.39 s (Table 5.4)"],
        ["NFR4", "Secure against common web attacks", "Met", "Table 4.18; TC11, TC17–TC24, TC29"],
        ["NFR5", "Works in common browsers", "Partly met", "Chrome and Edge tested; Firefox and Safari not tested"],
        ["NFR6", "Runs on XAMPP without extra packages", "Met", "No Composer packages; Excel read with built-in PHP"],
        ["NFR7", "Credit the data sources and respect their licences", "Met", "Footer and About page"],
        ["NFR8", "Easy to use for non-technical staff", "Partly met", "Preview and undo help, but no usability test with real users"],
    ], widths=[0.5, 2.6, 0.9, 2.6], size=9),
    NOTE("[Check that the requirement wording in Table 5.10 matches the objectives and requirements written in Chapter 1, and adjust the IDs if needed.]"),

    H2("5.7 Summary"),
    P(f"This chapter described the sixteen pages of the system with their screenshots and code, and the testing that was "
      f"done on them. {passed} of {len(cases)} test cases passed in the final run, the pages responded in under half a second, "
      "and the site worked in Chrome and Edge on desktop and phone screens. Six defects were found and fixed along the way. "
      "The system meets nineteen of its twenty-two requirements fully and the remaining three in part. Chapter 6 draws the "
      "conclusions of the project and suggests how it could be taken further."),
]
