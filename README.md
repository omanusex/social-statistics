# Social Media Statistics Observatory

A bilingual (English / Arabic) website that shows social media statistics for **Oman** and other
countries, filtered by country, statistic type, device and period. Graduation project.

## Data sources

| Source | What it gives | How it is read |
|---|---|---|
| [StatCounter Global Stats](https://gs.statcounter.com/social-media-stats) | Monthly share of website traffic referred by each social platform, per country and device | CSV download (automatic sync or uploaded file) – CC BY-SA 3.0 |
| [World Bank Open Data](https://data.worldbank.org) | Internet users (% of population) and population per year | REST API, JSON (`IT.NET.USER.ZS`, `SP.POP.TOTL`) – CC BY 4.0 |
| Local questionnaire | Platforms used by young people in Oman, purpose, hours per day, late-night use | Excel / CSV import + online form |
| Manual upload | Any other statistic (e.g. platform users from DataReportal) | Excel / CSV template |

## Features

**Public site**
- Home: key numbers, platform shares, 12-month trend, map of the leading platform per country, automatic findings
- Explore: filters (country, statistic type, device, period, platforms, 3-month average), line/bar chart, data table, CSV / PNG download, shareable link
- Compare: up to 6 countries over time, ranking of all countries with Gulf/Arab averages, internet use vs platform share scatter chart with correlation
- Local study: questionnaire results filtered by age group and gender
- Online questionnaire (anonymous, with consent and anti-spam checks)
- About: sources, licences, definitions, methodology, limitations, privacy
- English / Arabic with right-to-left layout

**Admin panel**
- Secure sign-in (hashed passwords, lock-out after 5 failed attempts, session timeout, CSRF protection)
- Update from StatCounter and the World Bank with one click
- Upload CSV / Excel files with a validation preview before saving
- Import history with undo (rollback)
- Browse, filter, download and delete stored values
- Questionnaire management (open/close, export, delete)
- Accounts with two roles (administrator, data editor)
- Audit log and database backup

## Technology

PHP 8.2 (no framework), MySQL / MariaDB (PDO with prepared statements), Bootstrap 5, Chart.js 4,
jsVectorMap. Excel files are read with PHP's built-in ZipArchive + SimpleXML, so no Composer packages are needed.

## Installation (XAMPP)

1. Copy the folder to `C:\xampp\htdocs\social-statistics`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. If your MySQL user or password is not `root` / empty, edit `includes/config.php`.
4. Open <http://localhost/social-statistics/> – you are sent to the installer. Enter the administrator's name, email and password.
5. Sign in at `/admin`, open **Update from APIs** and download the StatCounter and World Bank data.

To reinstall from scratch, delete `storage/installed.lock` (this erases all data).

## Folder structure

```
admin/            admin panel pages
api/              JSON endpoints used by the charts (series, compare, map)
assets/           CSS, JavaScript, images and the front-end libraries
database/         schema.sql, seed.sql and the import templates
includes/         configuration, layout, helpers and classes
includes/classes/ Database, Auth, Lang, StatRepository, SurveyRepository, DataImporter,
                  StatCounterClient, WorldBankClient, SpreadsheetReader, InsightEngine, BackupService ...
lang/             en.php and ar.php translations
storage/          uploads and backups (not reachable from the web)
```

## Import templates

`database/templates/stats_template.csv`

| country_iso2 | platform | metric | device | period | value |
|---|---|---|---|---|---|
| OM | instagram | platform_users | all | 2026 | … |

`database/templates/survey_template.csv`

| age_group | gender | platforms_used | favourite | main_purpose | daily_hours | late_night_use |
|---|---|---|---|---|---|---|
| 16-18 | female | snapchat;instagram | snapchat | entertainment | 3-4 | 1 |

`database/templates/sample_survey.csv` contains 50 demonstration answers generated from the summary
figures of the original pilot questionnaire. Replace them with the real answers before submission.
