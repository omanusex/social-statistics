-- Reference data: countries, platforms, statistic types, sources, settings
SET NAMES utf8mb4;

INSERT INTO countries (iso2, iso3, name_en, name_ar, region, is_default) VALUES
('OM', 'OMN', 'Oman',                 'عُمان',               'GCC',   1),
('SA', 'SAU', 'Saudi Arabia',         'السعودية',            'GCC',   0),
('AE', 'ARE', 'United Arab Emirates', 'الإمارات',            'GCC',   0),
('QA', 'QAT', 'Qatar',                'قطر',                 'GCC',   0),
('KW', 'KWT', 'Kuwait',               'الكويت',              'GCC',   0),
('BH', 'BHR', 'Bahrain',              'البحرين',             'GCC',   0),
('EG', 'EGY', 'Egypt',                'مصر',                 'Arab',  0),
('JO', 'JOR', 'Jordan',               'الأردن',              'Arab',  0),
('IQ', 'IRQ', 'Iraq',                 'العراق',              'Arab',  0),
('LB', 'LBN', 'Lebanon',              'لبنان',               'Arab',  0),
('MA', 'MAR', 'Morocco',              'المغرب',              'Arab',  0),
('DZ', 'DZA', 'Algeria',              'الجزائر',             'Arab',  0),
('TN', 'TUN', 'Tunisia',              'تونس',                'Arab',  0),
('WW', 'WLD', 'Worldwide',            'العالم',              'World', 0),
('US', 'USA', 'United States',        'الولايات المتحدة',    'World', 0),
('GB', 'GBR', 'United Kingdom',       'المملكة المتحدة',     'World', 0),
('IN', 'IND', 'India',                'الهند',               'World', 0);

INSERT INTO platforms (slug, name, statcounter_name, color, sort_order) VALUES
('facebook',  'Facebook',  'Facebook',  '#1877F2', 1),
('instagram', 'Instagram', 'Instagram', '#C13584', 2),
('youtube',   'YouTube',   'YouTube',   '#E52D27', 3),
('x',         'X (Twitter)','Twitter',  '#222222', 4),
('pinterest', 'Pinterest', 'Pinterest', '#F39C12', 5),
('linkedin',  'LinkedIn',  'LinkedIn',  '#20A39E', 6),
('reddit',    'Reddit',    'reddit',    '#FF6B35', 7),
('tumblr',    'Tumblr',    'Tumblr',    '#6C5CE7', 8),
('snapchat',  'Snapchat',  NULL,        '#F5C400', 9),
('tiktok',    'TikTok',    NULL,        '#00B8C4', 10),
('whatsapp',  'WhatsApp',  NULL,        '#25D366', 11),
('other',     'Other',     'Other',     '#ADB5BD', 99);

INSERT INTO metrics (code, name_en, name_ar, unit, granularity, per_platform, description_en, description_ar) VALUES
('traffic_share', 'Social media traffic share', 'حصة زيارات وسائل التواصل', '%', 'monthly', 1,
 'Share of website visits referred by each social media platform (StatCounter). It shows which platforms send people to websites, not how many people use each platform.',
 'نسبة زيارات المواقع القادمة من كل منصة تواصل اجتماعي (StatCounter). تبيّن المنصات التي ترسل الزوار إلى المواقع، وليس عدد مستخدمي كل منصة.'),
('internet_users', 'Internet users (% of population)', 'مستخدمو الإنترنت (% من السكان)', '%', 'yearly', 0,
 'Individuals who used the Internet in the last 3 months, as a percentage of the population (World Bank, indicator IT.NET.USER.ZS).',
 'الأفراد الذين استخدموا الإنترنت خلال آخر 3 أشهر كنسبة من السكان (البنك الدولي، المؤشر IT.NET.USER.ZS).'),
('population', 'Population', 'عدد السكان', 'people', 'yearly', 0,
 'Total population (World Bank, indicator SP.POP.TOTL).',
 'إجمالي عدد السكان (البنك الدولي، المؤشر SP.POP.TOTL).'),
('platform_users', 'Platform users', 'عدد مستخدمي المنصة', 'people', 'yearly', 1,
 'Number of users of each platform, entered from published reports (e.g. DataReportal) using the Excel template.',
 'عدد مستخدمي كل منصة، يُدخل من التقارير المنشورة (مثل DataReportal) باستخدام قالب Excel.');

INSERT INTO sources (code, name, url, license) VALUES
('statcounter', 'StatCounter Global Stats', 'https://gs.statcounter.com/social-media-stats', 'CC BY-SA 3.0'),
('worldbank',   'World Bank Open Data',     'https://data.worldbank.org',                    'CC BY 4.0'),
('survey',      'Local questionnaire',      '',                                              'Collected for academic purposes'),
('manual',      'Manual upload (Excel/CSV)','',                                              'As stated by the uploader');

INSERT INTO settings (name, value) VALUES
('survey_open', '1'),
('survey_is_demo', '0');
