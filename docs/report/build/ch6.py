import json
import os
from docbuilder import *

R = json.load(open(os.path.join(os.path.dirname(__file__), "..", "test_results.json")))
n = len(R["cases"])

CH6 = [
    CHAPTER(6),
    H1("Chapter 6: Conclusion and Recommendations"),

    H2("6.1 Conclusion"),
    P("The aim of this project was to build a bilingual website that collects social media statistics from outside sources "
      "and lets people explore them by country and by type of statistic, with Oman as the main focus. A pilot version had "
      "shown the idea with fixed numbers on a static page; the project set out to replace those numbers with real, "
      "updatable data and to make them easy to compare."),
    P("The finished system stores about 21,300 values. Monthly social media traffic shares were downloaded from StatCounter "
      "for seventeen countries and regions and three device groups, from January 2023 to September 2026 (and from January "
      "2022 for Oman). Yearly internet use and population figures were read from the World Bank API for 2010 to 2025. A "
      "questionnaire module adds the platforms that public statistics miss, such as TikTok, Snapchat and WhatsApp. "
      f"Visitors can filter, compare and download the data in English or Arabic, and staff can update it without editing "
      f"code. All {n} test cases passed, and the pages respond in under half a second on a local server."),
    P("The main contribution of the project is that it puts scattered public figures about social media in Oman into one "
      "place, in a form that can be checked. Every number on the site shows its source and licence, the method is explained, "
      "and the limits of each statistic are stated next to it. The single statistics table also makes the system easy to "
      "extend: a new source only needs a new importer, and the existing filters, charts and downloads work with it at once."),
    P("The system is not tied to the college. A school could use it to discuss online habits with students and parents, using "
      "its own questionnaire. A marketing or media team could use the comparison and device figures to decide where to "
      "publish in Oman and the Gulf. Researchers could download the cleaned monthly series instead of copying values from "
      "charts by hand. Because the country list is a database table, the same system could serve any other country by "
      "changing the default country and adding its questionnaire."),

    H2("6.2 Recommendations and future work"),
    BUL([
        "A scheduled task (cron on Linux or Task Scheduler on Windows) could run the StatCounter and "
        "World Bank updates on the first day of each month, so the data never falls behind.",
        "Yearly user numbers per platform from published reports such as DataReportal (2025) can already "
        "be entered with the Excel template; a future version could add sources with official APIs, for example Google Trends "
        "for search interest by platform.",
        "The questionnaire should be run with a planned sample across several schools and colleges "
        "in Oman, so that the local results describe a known population rather than a group of volunteers.",
        "Deploying the site on a hosting service with HTTPS would let people outside the college use it, "
        "and would make it possible to test it under real traffic.",
        "A short test with five or six real users, including a non-technical data editor, would show "
        "where the admin panel is hard to understand.",
        "The JSON API already returns everything the charts need, so a small mobile app could be "
        "built on top of it without changing the server.",
        "A trend line for the next three to six months could be added to the Explore page, with a "
        "clear warning that it is an estimate.",
    ]),

    H2("6.3 Limitations"),
    BUL([
        "StatCounter measures the share of website visits that each platform refers, not the number of users or the time "
        "spent in each app. A platform that keeps people inside its own app, such as TikTok or WhatsApp, appears small or not "
        "at all.",
        "Monthly values for small countries can jump from one month to the next because they are based on fewer visits; the "
        "three-month average reduces this but does not remove it.",
        "The StatCounter download uses the same public CSV address as the StatCounter website. It is not a documented API, so "
        "a change on their side could stop the automatic update until the client class is adjusted. Manual CSV upload "
        "remains available as a fallback.",
        "The questionnaire answers currently in the system are demonstration data based on the pilot's summary figures, and "
        "the questionnaire itself uses a convenience sample.",
        "The system runs on a local XAMPP server. It has not been hosted online, tested with many users at once, or tested in "
        "Firefox and Safari.",
        "Updates are started by hand from the admin panel; there is no scheduled update yet.",
    ]),

    H2("6.4 Impact of the project"),
    H3("6.4.1 Social impact"),
    P("Discussions about young people and social media in Oman often rely on impressions or on figures from other countries. "
      "The system gives parents, teachers and students local numbers they can look at together, including how late at night "
      "young respondents use social media and how many hours they spend on it. Because the site is in Arabic as well as "
      "English, it is usable by parents and teachers who prefer Arabic, which the pilot version was not."),
    H3("6.4.2 Ethical impact"),
    P("The questionnaire collects opinions from young people, some of whom may be under eighteen, so it was designed to collect "
      "as little as possible. It asks for an age group rather than a date of birth, and it stores no name, e-mail address or "
      "IP address with an answer. Respondents must tick a consent box that explains the academic purpose, and the "
      "administrator can close the questionnaire at any time. Answers are only shown as totals and percentages, never one by "
      "one, so no individual can be recognised on the public pages. Before real answers are collected, the questionnaire "
      "follows the college's research ethics approval procedure. All visitors see the same data, and the written findings "
      "are produced by fixed rules, so the system does not favour any platform or country."),
    NOTE("[If ethics approval has already been granted, add the approval reference or date here.]"),
    H3("6.4.3 Legal impact"),
    P("All data sources are credited with their licences on every page that uses them. StatCounter data is published under "
      "the Creative Commons Attribution-ShareAlike 3.0 licence and World Bank data under Creative Commons Attribution 4.0, "
      "both of which allow reuse with attribution. The libraries used (Bootstrap, Chart.js and jsVectorMap) are released "
      "under the MIT licence, and books, articles and web pages used in this report are referenced. The handling of "
      "questionnaire answers also follows the principles of Oman's Personal Data Protection Law, issued by Royal Decree No. "
      "6/2022: because no personal data that can identify a person is stored, the risk to respondents is low."),
    H3("6.4.4 Sustainability"),
    P("Several choices make the system sustainable in the long run. It is built only on free, open-source software (Apache, "
      "PHP, MariaDB) that runs on modest hardware and on cheap shared hosting. It reuses data that public bodies already "
      "publish instead of collecting it again. The questionnaire is online, which saves the paper of printed forms. The code "
      "has no framework or package manager to keep updated, and the setup takes a few minutes with the installer, so a "
      "future student or staff member can take over the project. Finally, every import can be undone and the database can "
      "be backed up from the admin panel, which protects the data that has been collected."),

    H2("6.5 Reflective writing"),
    P("This section reflects on the development of the project using the six stages of Gibbs' reflective cycle (Gibbs, "
      "1988): description, feelings, evaluation, analysis, conclusion and action plan."),

    H3("6.5.1 Description of the project and its functional units"),
    P("The purpose of the project was to develop a website that shows social media statistics for Oman and other countries, "
      "with filters for country, statistic type, device and period. The target users were students, parents, teachers, "
      "researchers and media teams, and the staff who keep the data up to date. The project grew out of a pilot that showed "
      "survey results on a static page."),
    P("The system follows a three-tier architecture with three functional units. (1) A presentation unit made of PHP pages, "
      "Bootstrap and Chart.js shows the data in English or Arabic. (2) A logic unit of fourteen PHP classes handles the "
      "repositories, the importers, the API clients and the rules that write the findings. (3) A data unit in MariaDB holds "
      "eleven tables, with one central table for every statistic. The application was written in PHP 8.2 on XAMPP, read "
      "StatCounter CSV files and the World Bank REST API through cURL, and read Excel files with PHP's own ZipArchive and "
      "SimpleXML instead of an external library."),

    H3("6.5.2 Awareness of the issues encountered"),
    P("The first unexpected behaviour appeared on the Explore page. The JSON API returned 24 months of data for nine platforms, "
      "yet the chart stayed empty. This was frustrating at first, because every part had worked when tested on its own. The "
      "browser console showed the message \"can't acquire context from the given item\", which led to the hypothesis that the "
      "chart library was not receiving a canvas. Checking the page source confirmed it: a drop-down list and the canvas had "
      "the same id, and the script found the list first."),
    P("The second issue was more worrying. While testing the backup feature, which creates and drops a full copy of the "
      "database, the database server stopped with \"MySQL server has gone away\" and kept stopping after each restart. The "
      "first thought was that the test itself had damaged the project data. The developer read the MariaDB error log, which "
      "showed InnoDB warnings about a \"log sequence number in the future\" that went back several weeks, before the project "
      "existed. After the server was restarted, `CHECK TABLE` reported every project table as OK. The feeling moved from "
      "alarm to relief, and then to caution about what the tests were allowed to do on a shared server."),
    P("A third issue was confusion about the data itself. StatCounter's figures for Oman jumped sharply between months; "
      "YouTube rose to 23% in one month and X to 26% in another, then both fell back. It looked like an import error. The "
      "developer downloaded the original CSV file for January to September 2025 and compared its 81 values with the "
      "database. All 81 matched, which showed that the import was correct and the jumps were in the source."),

    H3("6.5.3 Evaluation and reflection"),
    P(f"Most features worked reliably. All {n} automated test cases passed in the final run, the full StatCounter update "
      "fetched 51 files and stored 20,475 values in 1 minute 49 seconds without a failed request, and the pages answered in "
      "0.26 to 0.39 seconds on average. Mobile and desktop layouts showed no errors in Chrome or Edge. The decision that "
      "contributed most to this was storing every statistic in one table with the same columns: the filters, charts, "
      "downloads and comparison features were written once and worked for every source."),
    P("Some parts did not work at first. Test TC11 failed in the first run, because a value from the address bar was printed "
      "inside a script block without being checked, and the Arabic version displayed number ranges backwards. The cause in "
      "both cases was the same habit: trusting that a value would look the way it was expected to look. The questionnaire "
      "module works, but it still holds demonstration data, so the local study cannot yet support any real conclusion."),

    H3("6.5.4 Critical analysis"),
    P("Some design patterns behaved differently in practice than in theory. In theory, a nullable foreign key is the correct "
      "way to model a statistic that belongs to a whole country rather than a platform. In practice, MySQL treats empty values "
      "as different from each other, so the unique key would not stop duplicates; a generated column had to be added. The "
      "upsert with ON DUPLICATE KEY UPDATE succeeded exactly as designed and made repeated updates safe."),
    P("Resource constraints shaped many decisions. Commercial sources such as Similarweb offer richer data through paid "
      "APIs, which a student project cannot afford, so the project combined free sources and explained their limits instead. "
      "XAMPP was required because it is what the labs provide, which ruled out frameworks that need extra setup and led to "
      "reading Excel files without a library. Time was the other limit: the backup test that stressed the database had to be "
      "replaced with a lighter check rather than debugging the damaged server."),
    P("Feedback during development also changed the design. A review of the first plan asked for MySQL instead of SQLite, "
      "for Oman to be the main focus while keeping other countries available, and for a full Arabic interface. Later reviews "
      "asked for the demonstration notice to be removed from the public page and for the diagrams to look like standard UML "
      "rather than generated output. Each request was smaller than it seemed at first but had effects elsewhere; the Arabic "
      "interface, for example, needed a second Bootstrap build and changes to the chart labels."),
    P("The project mirrors real work in data journalism and market research, where figures from several sources must be "
      "cleaned, combined and explained honestly. It addresses a genuine need, local and comparable numbers about social media "
      "in Oman, and its impact can be measured: about 21,300 values from three sources are available in one place, with "
      "every update logged."),

    H3("6.5.5 Conclusion"),
    P("The project bridged a gap between academic knowledge and implementation. Database normalisation, the OWASP list and "
      "the idea of test cases were familiar from courses, but applying them showed where they meet real constraints: a "
      "nullable key that breaks a unique index, a security test that fails on a value nobody expected, a server that fails for "
      "reasons outside the code. Debugging also became more systematic, moving from guessing to reading the console, the "
      "server log and the database before changing any code."),
    P("Soft skills developed too, especially negotiating scope. Choosing between SQLite and MySQL, or between hiding and "
      "showing the demonstration notice, meant explaining the trade-off clearly and then accepting the reviewer's decision. "
      "The view of software quality changed from \"the page works\" to \"the page works, fails safely and can prove it\", "
      "which is what the automated tests now show. This experience is relevant to roles in web development and data "
      "analysis, where building a dashboard is only half the job and explaining where its numbers come from is the other "
      "half."),

    H3("6.5.6 Action plan"),
    BUL([
        "In the next project, automated tests will be written alongside the first "
        "feature, using PHPUnit for classes and a browser tool for pages, instead of after most pages are finished.",
        "Every value from a request will be checked against an expected "
        "pattern before use, and the OWASP Top 10 course material will be reviewed within the next two months.",
        "Before any test that stresses the database, a backup of the whole server "
        "will be taken, and heavy tests will run in a separate database server or a Docker container.",
        "Within three months, the system will be rebuilt as a practice "
        "exercise in Laravel, with GitHub Actions running the tests on every change.",
        "Before submitting future work, at least five people outside the project will try the main "
        "tasks while their difficulties are noted.",
    ]),

    H2("6.6 Source code"),
    P("The complete source code of the system, including the database schema, the import templates and the test script, is "
      "available in the following GitHub repository:"),
    NOTE("[GitHub repository link]"),

    H2("References"),
    REF("Creative Commons (n.d.) Attribution-ShareAlike 3.0 Unported (CC BY-SA 3.0). Available at: https://creativecommons.org/licenses/by-sa/3.0/ (Accessed: 28 October 2026)."),
    REF("Creative Commons (n.d.) Attribution 4.0 International (CC BY 4.0). Available at: https://creativecommons.org/licenses/by/4.0/ (Accessed: 28 October 2026)."),
    REF("DataReportal (2025) Digital 2025: Oman. Available at: https://datareportal.com/reports/digital-2025-oman (Accessed: 28 October 2026)."),
    REF("Gibbs, G. (1988) Learning by Doing: A Guide to Teaching and Learning Methods. Oxford: Further Education Unit, Oxford Polytechnic."),
    REF("OWASP Foundation (2021) OWASP Top 10:2021. Available at: https://owasp.org/Top10/ (Accessed: 28 October 2026)."),
    REF("Sommerville, I. (2016) Software Engineering. 10th edn. Harlow: Pearson."),
    REF("StatCounter (2026) Social Media Stats Worldwide. StatCounter Global Stats. Available at: https://gs.statcounter.com/social-media-stats (Accessed: 28 October 2026)."),
    REF("Sultanate of Oman (2022) Royal Decree No. 6/2022 Promulgating the Personal Data Protection Law. Muscat: Official Gazette."),
    REF("World Bank (2026) World Bank Open Data API (v2). Available at: https://datahelpdesk.worldbank.org/knowledgebase/articles/889392 (Accessed: 28 October 2026)."),
]
