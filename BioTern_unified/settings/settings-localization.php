<?php
require_once dirname(__DIR__) . '/config/db.php';
$page_title = 'Localization Settings';
$page_styles = ['assets/css/settings-customizer-like.css'];
include 'includes/header.php';
?>

<div class="main-content d-flex settings-theme-customizer">                <!-- [ Content Sidebar ] start -->
                <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
                    <div class="content-sidebar-header sticky-top hstack justify-content-between">
                        <h4 class="fw-bolder mb-0">Settings</h4>
                        <a href="javascript:void(0);" class="app-sidebar-close-trigger d-flex">
                            <i class="feather-x"></i>
                        </a>
                    </div>
                    <div class="content-sidebar-body">
                        <ul class="nav flex-column nxl-content-sidebar-item">
                            <li class="nav-item">
                                <a class="nav-link" href="settings-general.php">
                                    <i class="feather-airplay"></i>
                                    <span>General</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-seo.php">
                                    <i class="feather-search"></i>
                                    <span>SEO</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-tags.php">
                                    <i class="feather-tag"></i>
                                    <span>Tags</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-email.php">
                                    <i class="feather-mail"></i>
                                    <span>Email</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-tasks.php">
                                    <i class="feather-check-circle"></i>
                                    <span>Tasks</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-ojt.php">
                                    <i class="feather-crosshair"></i>
                                    <span>Leads</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-support.php">
                                    <i class="feather-life-buoy"></i>
                                    <span>Support</span>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link" href="settings-students.php">
                                    <i class="feather-users"></i>
                                    <span>Students</span>
                                </a>
                            </li>


                            <li class="nav-item">
                                <a class="nav-link" href="settings-miscellaneous.php">
                                    <i class="feather-cast"></i>
                                    <span>Miscellaneous</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="theme-customizer.php">
                                    <i class="feather-settings"></i>
                                    <span>Theme Customizer</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- [ Content Sidebar  ] end -->
                <!-- [ Main Area  ] start -->
                <div class="content-area" data-scrollbar-target="#psScrollbarInit">
                    <div class="content-area-header sticky-top">
                        <div class="page-header-left">
                            <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                                <i class="feather-align-left fs-24"></i>
                            </a>
                        </div>
                        <div class="page-header-right ms-auto">
                            <div class="d-flex align-items-center gap-3 page-header-right-items-wrapper">
                                <a href="javascript:void(0);" class="text-danger">Cancel</a>
                                <a href="javascript:void(0);" class="btn btn-primary successAlertMessage">
                                    <i class="feather-save me-2"></i>
                                    <span>Save Changes</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="content-area-body">
                        <div class="card mb-0">
                            <div class="card-body">
                                <div class="mb-5">
                                    <label class="form-label">Disable Languages </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Disable Languages [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label"> Output client PDF documents from admin area in client language </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted"> Output client PDF documents from admin area in client language [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Date Format </label>
                                    <select class="form-select" data-select2-selector="default">
                                        <option value="d-m-Y" selected>d-m-Y</option>
                                        <option value="d/m/Y">d/m/Y</option>
                                        <option value="m-d-Y">m-d-Y</option>
                                        <option value="m.d.Y">m.d.Y</option>
                                        <option value="m/d/Y">m/d/Y</option>
                                        <option value="Y-m-d">Y-m-d</option>
                                        <option value="d.m.Y">d.m.Y</option>
                                    </select>
                                    <small class="form-text text-muted">Date Format [Ex: d/m/Y/m-d-Y/m.d.Y]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Default Timezone</label>

                                    <select class="form-control" data-select2-selector="tzone">
                                        <option data-tzone="feather-moon">(GMT -12:00) Eniwetok, Kwajalein</option>
                                        <option data-tzone="feather-moon">(GMT -11:00) Midway Island, Samoa</option>
                                        <option data-tzone="feather-moon">(GMT -10:00) Hawaii</option>
                                        <option data-tzone="feather-moon">(GMT -9:30) Taiohae</option>
                                        <option data-tzone="feather-moon">(GMT -9:00) Alaska</option>
                                        <option data-tzone="feather-moon">(GMT -8:00) Pacific Time (US &amp; Canada)</option>
                                        <option data-tzone="feather-moon">(GMT -7:00) Mountain Time (US &amp; Canada)</option>
                                        <option data-tzone="feather-moon">(GMT -6:00) Central Time (US &amp; Canada), Mexico City</option>
                                        <option data-tzone="feather-moon">(GMT -5:00) Eastern Time (US &amp; Canada), Bogota, Lima</option>
                                        <option data-tzone="feather-moon">(GMT -4:30) Caracas</option>
                                        <option data-tzone="feather-moon">(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
                                        <option data-tzone="feather-moon">(GMT -3:30) Newfoundland</option>
                                        <option data-tzone="feather-moon">(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
                                        <option data-tzone="feather-moon">(GMT -2:00) Mid-Atlantic</option>
                                        <option data-tzone="feather-moon">(GMT -1:00) Azores, Cape Verde Islands</option>
                                        <option data-tzone="feather-sunrise" selected>(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
                                        <option data-tzone="feather-sun">(GMT +1:00) Brussels, Copenhagen, Madrid, Paris</option>
                                        <option data-tzone="feather-sun">(GMT +2:00) Kaliningrad, South Africa</option>
                                        <option data-tzone="feather-sun">(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
                                        <option data-tzone="feather-sun">(GMT +3:30) Tehran</option>
                                        <option data-tzone="feather-sun">(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
                                        <option data-tzone="feather-sun">(GMT +4:30) Kabul</option>
                                        <option data-tzone="feather-sun">(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
                                        <option data-tzone="feather-sun">(GMT +5:30) Bombay, Calcutta, Madras, New Delhi</option>
                                        <option data-tzone="feather-sun">(GMT +5:45) Kathmandu, Pokhara</option>
                                        <option data-tzone="feather-sun">(GMT +6:00) Almaty, Dhaka, Colombo</option>
                                        <option data-tzone="feather-sun">(GMT +6:30) Yangon, Mandalay</option>
                                        <option data-tzone="feather-sun">(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
                                        <option data-tzone="feather-sun">(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
                                        <option data-tzone="feather-sun">(GMT +8:45) Eucla</option>
                                        <option data-tzone="feather-sun">(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
                                        <option data-tzone="feather-sun">(GMT +9:30) Adelaide, Darwin</option>
                                        <option data-tzone="feather-sun">(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
                                        <option data-tzone="feather-sun">(GMT +10:30) Lord Howe Island</option>
                                        <option data-tzone="feather-sun">(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
                                        <option data-tzone="feather-sun">(GMT +11:30) Norfolk Island</option>
                                        <option data-tzone="feather-sun">(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
                                        <option data-tzone="feather-sun">(GMT +12:45) Chatham Islands</option>
                                        <option data-tzone="feather-sun">(GMT +13:00) Apia, Nukualofa</option>
                                        <option data-tzone="feather-sun">(GMT +14:00) Line Islands, Tokelau</option>
                                    </select>
                                    <small class="form-text text-muted">Default Timezone [Ex: (GMT) Western Europe Time, London, Lisbon, Casablanca]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Default Language </label>
                                    <select class="form-control" data-select2-selector="language" multiple>
                                        <option data-language="bg-primary">Afrikaans</option>
                                        <option data-language="bg-warning">Albanian - shqip</option>
                                        <option data-language="bg-cyan">Amharic - áŠ áˆ›áˆ­áŠ›</option>
                                        <option data-language="bg-green">Arabic - Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©</option>
                                        <option data-language="bg-black">Aragonese - aragonÃ©s</option>
                                        <option data-language="bg-teal">Armenian - Õ°Õ¡ÕµÕ¥Ö€Õ¥Õ¶</option>
                                        <option data-language="bg-success">Asturian - asturianu</option>
                                        <option data-language="bg-cyan">Azerbaijani - azÉ™rbaycan dili</option>
                                        <option data-language="bg-indigo">Basque - euskara</option>
                                        <option data-language="bg-teal">Belarusian - Ð±ÐµÐ»Ð°Ñ€ÑƒÑÐºÐ°Ñ</option>
                                        <option data-language="bg-black">Bengali - à¦¬à¦¾à¦‚à¦²à¦¾</option>
                                        <option data-language="bg-green">Bosnian - bosanski</option>
                                        <option data-language="bg-primary">Breton - brezhoneg</option>
                                        <option data-language="bg-warning">Bulgarian - Ð±ÑŠÐ»Ð³Ð°Ñ€ÑÐºÐ¸</option>
                                        <option data-language="bg-teal">Catalan - catalÃ </option>
                                        <option data-language="bg-black">Central Kurdish - Ú©ÙˆØ±Ø¯ÛŒ (Ø¯Û•Ø³ØªÙ†ÙˆØ³ÛŒ Ø¹Û•Ø±Û•Ø¨ÛŒ)</option>
                                        <option data-language="bg-green">Chinese - ä¸­æ–‡</option>
                                        <option data-language="bg-cyan">Chinese (Hong Kong) - ä¸­æ–‡ï¼ˆé¦™æ¸¯ï¼‰</option>
                                        <option data-language="bg-primary">Chinese (Simplified) - ä¸­æ–‡ï¼ˆç®€ä½“ï¼‰</option>
                                        <option data-language="bg-danger">Chinese (Traditional) - ä¸­æ–‡ï¼ˆç¹é«”ï¼‰</option>
                                        <option data-language="bg-cyan">Corsican</option>
                                        <option data-language="bg-black">Croatian - hrvatski</option>
                                        <option data-language="bg-warning">Czech - ÄeÅ¡tina</option>
                                        <option data-language="bg-primary">Danish - dansk</option>
                                        <option data-language="bg-teal">Dutch - Nederlands</option>
                                        <option data-language="bg-danger" selected>English</option>
                                        <option data-language="bg-green">English (Australia)</option>
                                        <option data-language="bg-black">English (Canada)</option>
                                        <option data-language="bg-cyan">English (India)</option>
                                        <option data-language="bg-primary">English (New Zealand)</option>
                                        <option data-language="bg-indigo">English (South Africa)</option>
                                        <option data-language="bg-black">English (United Kingdom)</option>
                                        <option data-language="bg-teal">English (United States)</option>
                                        <option data-language="bg-green">Esperanto - esperanto</option>
                                        <option data-language="bg-cyan">Estonian - eesti</option>
                                        <option data-language="bg-primary">Faroese - fÃ¸royskt</option>
                                        <option data-language="bg-black">Filipino</option>
                                        <option data-language="bg-cyan">Finnish - suomi</option>
                                        <option data-language="bg-primary">French - franÃ§ais</option>
                                        <option data-language="bg-success">French (Canada) - franÃ§ais (Canada)</option>
                                        <option data-language="bg-warning">French (France) - franÃ§ais (France)</option>
                                        <option data-language="bg-black">French (Switzerland) - franÃ§ais (Suisse)</option>
                                        <option data-language="bg-primary">Galician - galego</option>
                                        <option data-language="bg-teal">Georgian - áƒ¥áƒáƒ áƒ—áƒ£áƒšáƒ˜</option>
                                        <option data-language="bg-black">German - Deutsch</option>
                                        <option data-language="bg-green">German (Austria) - Deutsch (Ã–sterreich)</option>
                                        <option data-language="bg-danger">German (Germany) - Deutsch (Deutschland)</option>
                                        <option data-language="bg-indigo">German (Liechtenstein) - Deutsch (Liechtenstein)</option>
                                        <option data-language="bg-cyan">German (Switzerland) - Deutsch (Schweiz)</option>
                                        <option data-language="bg-primary">Greek - Î•Î»Î»Î·Î½Î¹ÎºÎ¬</option>
                                        <option data-language="bg-green">Guarani</option>
                                        <option data-language="bg-teal">Gujarati - àª—à«àªœàª°àª¾àª¤à«€</option>
                                        <option data-language="bg-success">Hausa</option>
                                        <option data-language="bg-primary">Hawaiian - 'ÅŒlelo Hawai'i</option>
                                        <option data-language="bg-cyan">Hebrew - ×¢×‘×¨×™×ª</option>
                                        <option data-language="bg-warning" selected>Hindi - à¤¹à¤¿à¤¨à¥à¤¦à¥€</option>
                                        <option data-language="bg-green">Hungarian - magyar</option>
                                        <option data-language="bg-black">Icelandic - Ã­slenska</option>
                                        <option data-language="bg-danger">Indonesian - Indonesia</option>
                                        <option data-language="bg-primary">Interlingua</option>
                                        <option data-language="bg-green">Irish - Gaeilge</option>
                                        <option data-language="bg-success">Italian - italiano</option>
                                        <option data-language="bg-cyan">Italian (Italy) - italiano (Italia)</option>
                                        <option data-language="bg-teal">Italian (Switzerland) - italiano (Svizzera)</option>
                                        <option data-language="bg-indigo">Japanese - æ—¥æœ¬èªž</option>
                                        <option data-language="bg-primary">Kannada - à²•à²¨à³à²¨à²¡</option>
                                        <option data-language="bg-cyan">Kazakh - Ò›Ð°Ð·Ð°Ò› Ñ‚Ñ–Ð»Ñ–</option>
                                        <option data-language="bg-black">Khmer - ážáŸ’áž˜áŸ‚ážš</option>
                                        <option data-language="bg-primary">Korean - í•œêµ­ì–´</option>
                                        <option data-language="bg-warning">Kurdish - KurdÃ®</option>
                                        <option data-language="bg-cyan">Kyrgyz - ÐºÑ‹Ñ€Ð³Ñ‹Ð·Ñ‡Ð°</option>
                                        <option data-language="bg-danger">Lao - àº¥àº²àº§</option>
                                        <option data-language="bg-primary">Latin</option>
                                        <option data-language="bg-orange">Latvian - latvieÅ¡u</option>
                                        <option data-language="bg-green">Lingala - lingÃ¡la</option>
                                        <option data-language="bg-black">Lithuanian - lietuviÅ³</option>
                                        <option data-language="bg-primary">Macedonian - Ð¼Ð°ÐºÐµÐ´Ð¾Ð½ÑÐºÐ¸</option>
                                        <option data-language="bg-indigo">Malay - Bahasa Melayu</option>
                                        <option data-language="bg-green">Malayalam - à´®à´²à´¯à´¾à´³à´‚</option>
                                        <option data-language="bg-cyan">Maltese - Malti</option>
                                        <option data-language="bg-teal">Marathi - à¤®à¤°à¤¾à¤ à¥€</option>
                                        <option data-language="bg-primary">Mongolian - Ð¼Ð¾Ð½Ð³Ð¾Ð»</option>
                                        <option data-language="bg-danger">Nepali - à¤¨à¥‡à¤ªà¤¾à¤²à¥€</option>
                                        <option data-language="bg-green">Norwegian - norsk</option>
                                        <option data-language="bg-warning">Norwegian BokmÃ¥l - norsk bokmÃ¥l</option>
                                        <option data-language="bg-primary">Norwegian Nynorsk - nynorsk</option>
                                        <option data-language="bg-success">Occitan</option>
                                        <option data-language="bg-cyan">Oriya - à¬“à¬¡à¬¼à¬¿à¬†</option>
                                        <option data-language="bg-black">Oromo - Oromoo</option>
                                        <option data-language="bg-danger">Pashto - Ù¾ÚšØªÙˆ</option>
                                        <option data-language="bg-green">Persian - ÙØ§Ø±Ø³ÛŒ</option>
                                        <option data-language="bg-primary">Polish - polski</option>
                                        <option data-language="bg-teal">Portuguese - portuguÃªs</option>
                                        <option data-language="bg-danger">Portuguese (Brazil) - portuguÃªs (Brasil)</option>
                                        <option data-language="bg-black">Portuguese (Portugal) - portuguÃªs (Portugal)</option>
                                        <option data-language="bg-green">Punjabi - à¨ªà©°à¨œà¨¾à¨¬à©€</option>
                                        <option data-language="bg-indigo">Quechua</option>
                                        <option data-language="bg-success">Romanian - romÃ¢nÄƒ</option>
                                        <option data-language="bg-warning">Romanian (Moldova) - romÃ¢nÄƒ (Moldova)</option>
                                        <option data-language="bg-primary">Romansh - rumantsch</option>
                                        <option data-language="bg-danger">Russian - Ñ€ÑƒÑÑÐºÐ¸Ð¹</option>
                                        <option data-language="bg-green">Scottish Gaelic</option>
                                        <option data-language="bg-orange">Serbian - ÑÑ€Ð¿ÑÐºÐ¸</option>
                                        <option data-language="bg-teal">Serbo - Croatian</option>
                                        <option data-language="bg-primary">Shona - chiShona</option>
                                        <option data-language="bg-cyan">Sindhi</option>
                                        <option data-language="bg-black">Sinhala - à·ƒà·’à¶‚à·„à¶½</option>
                                        <option data-language="bg-warning">Slovak - slovenÄina</option>
                                        <option data-language="bg-danger">Slovenian - slovenÅ¡Äina</option>
                                        <option data-language="bg-green">Somali - Soomaali</option>
                                        <option data-language="bg-primary">Southern Sotho</option>
                                        <option data-language="bg-orange">Spanish - espaÃ±ol</option>
                                        <option data-language="bg-indigo">Spanish (Argentina) - espaÃ±ol (Argentina)</option>
                                        <option data-language="bg-green">Spanish (Latin America) - espaÃ±ol (LatinoamÃ©rica)</option>
                                        <option data-language="bg-cyan">Spanish (Mexico) - espaÃ±ol (MÃ©xico)</option>
                                        <option data-language="bg-black">Spanish (Spain) - espaÃ±ol (EspaÃ±a)</option>
                                        <option data-language="bg-success">Spanish (United States) - espaÃ±ol (Estados Unidos)</option>
                                        <option data-language="bg-primary">Sundanese</option>
                                        <option data-language="bg-teal">Swahili - Kiswahili</option>
                                        <option data-language="bg-green">Swedish - svenska</option>
                                        <option data-language="bg-cyan">Tajik - Ñ‚Ð¾Ò·Ð¸ÐºÓ£</option>
                                        <option data-language="bg-warning">Tamil - à®¤à®®à®¿à®´à¯</option>
                                        <option data-language="bg-primary">Tatar</option>
                                        <option data-language="bg-success">Telugu - à°¤à±†à°²à±à°—à±</option>
                                        <option data-language="bg-black">Thai - à¹„à¸—à¸¢</option>
                                        <option data-language="bg-green">Tigrinya - á‰µáŒáˆ­áŠ›</option>
                                        <option data-language="bg-teal">Tongan - lea fakatonga</option>
                                        <option data-language="bg-primary">Turkish - TÃ¼rkÃ§e</option>
                                        <option data-language="bg-danger">Turkmen</option>
                                        <option data-language="bg-indigo">Twi</option>
                                        <option data-language="bg-black">Ukrainian - ÑƒÐºÑ€Ð°Ñ—Ð½ÑÑŒÐºÐ°</option>
                                        <option data-language="bg-green">Urdu - Ø§Ø±Ø¯Ùˆ</option>
                                        <option data-language="bg-cyan">Uyghur</option>
                                        <option data-language="bg-primary">Uzbek - o'zbek</option>
                                        <option data-language="bg-success">Vietnamese - Tiáº¿ng Viá»‡t</option>
                                        <option data-language="bg-cyan">Walloon - wa</option>
                                        <option data-language="bg-primary">Welsh - Cymraeg</option>
                                        <option data-language="bg-teal">Western Frisian</option>
                                        <option data-language="bg-warning">Xhosa</option>
                                        <option data-language="bg-indigo">Yiddish</option>
                                        <option data-language="bg-green">Yoruba - ÃˆdÃ¨ YorÃ¹bÃ¡</option>
                                        <option data-language="bg-black">Zulu - isiZulu</option>
                                    </select>
                                    <small class="form-text text-muted">Default Language [Ex: English/Hindi - à¤¹à¤¿à¤¨à¥à¤¦à¥€]</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Default Country </label>
                                    <select class="form-control" data-select2-selector="country">
                                        <option data-country="af">Afghanistan</option>
                                        <option data-country="ax">Ã…land Islands</option>
                                        <option data-country="al">Albania</option>
                                        <option data-country="dz">Algeria</option>
                                        <option data-country="as">American Samoa</option>
                                        <option data-country="ad">Andorra</option>
                                        <option data-country="ao">Angola</option>
                                        <option data-country="ai">Anguilla</option>
                                        <option data-country="aq">Antarctica</option>
                                        <option data-country="ag">Antigua & Barbuda</option>
                                        <option data-country="ar">Argentina</option>
                                        <option data-country="am">Armenia</option>
                                        <option data-country="aw">Aruba</option>
                                        <option data-country="au">Australia</option>
                                        <option data-country="at">Austria</option>
                                        <option data-country="az">Azerbaijan</option>
                                        <option data-country="bs">Bahamas</option>
                                        <option data-country="bh">Bahrain</option>
                                        <option data-country="bd">Bangladesh</option>
                                        <option data-country="bb">Barbados</option>
                                        <option data-country="by">Belarus</option>
                                        <option data-country="be">Belgium</option>
                                        <option data-country="bz">Belize</option>
                                        <option data-country="bj">Benin</option>
                                        <option data-country="bm">Bermuda</option>
                                        <option data-country="bt">Bhutan</option>
                                        <option data-country="bo">Bolivia</option>
                                        <option data-country="bq">Caribbean Netherlands</option>
                                        <option data-country="ba">Bosnia & Herzegovina</option>
                                        <option data-country="bw">Botswana</option>
                                        <option data-country="bv">Bouvet Island</option>
                                        <option data-country="br">Brazil</option>
                                        <option data-country="io">British Indian Ocean Territory</option>
                                        <option data-country="bn">Brunei</option>
                                        <option data-country="bg">Bulgaria</option>
                                        <option data-country="bf">Burkina Faso</option>
                                        <option data-country="bi">Burundi</option>
                                        <option data-country="kh">Cambodia</option>
                                        <option data-country="cm">Cameroon</option>
                                        <option data-country="ca">Canada</option>
                                        <option data-country="cv">Cape Verde</option>
                                        <option data-country="ky">Cayman Islands</option>
                                        <option data-country="cf">Central African Republic</option>
                                        <option data-country="td">Chad</option>
                                        <option data-country="cl">Chile</option>
                                        <option data-country="cn">China</option>
                                        <option data-country="cx">Christmas Island</option>
                                        <option data-country="cc">Cocos (Keeling) Islands</option>
                                        <option data-country="co">Colombia</option>
                                        <option data-country="km">Comoros</option>
                                        <option data-country="cg">Congo - Brazzaville</option>
                                        <option data-country="cd">Congo - Kinshasa</option>
                                        <option data-country="ck">Cook Islands</option>
                                        <option data-country="cr">Costa Rica</option>
                                        <option data-country="ci">CÃ´te d'Ivoire</option>
                                        <option data-country="hr">Croatia</option>
                                        <option data-country="cu">Cuba</option>
                                        <option data-country="cu">CuraÃ§ao</option>
                                        <option data-country="cy">Cyprus</option>
                                        <option data-country="cz">Czechia</option>
                                        <option data-country="dk">Denmark</option>
                                        <option data-country="dj">Djibouti</option>
                                        <option data-country="dm">Dominica</option>
                                        <option data-country="do">Dominican Republic</option>
                                        <option data-country="ec">Ecuador</option>
                                        <option data-country="eg">Egypt</option>
                                        <option data-country="sv">El Salvador</option>
                                        <option data-country="gq">Equatorial Guinea</option>
                                        <option data-country="er">Eritrea</option>
                                        <option data-country="ee">Estonia</option>
                                        <option data-country="et">Ethiopia</option>
                                        <option data-country="fk">Falkland Islands (Islas Malvinas)</option>
                                        <option data-country="fo">Faroe Islands</option>
                                        <option data-country="fj">Fiji</option>
                                        <option data-country="fi">Finland</option>
                                        <option data-country="fr">France</option>
                                        <option data-country="gf">French Guiana</option>
                                        <option data-country="pf">French Polynesia</option>
                                        <option data-country="tf">French Southern Territories</option>
                                        <option data-country="ga">Gabon</option>
                                        <option data-country="gm">Gambia</option>
                                        <option data-country="ge">Georgia</option>
                                        <option data-country="de">Germany</option>
                                        <option data-country="gh">Ghana</option>
                                        <option data-country="gi">Gibraltar</option>
                                        <option data-country="gr">Greece</option>
                                        <option data-country="gl">Greenland</option>
                                        <option data-country="gd">Grenada</option>
                                        <option data-country="gp">Guadeloupe</option>
                                        <option data-country="gu">Guam</option>
                                        <option data-country="gt">Guatemala</option>
                                        <option data-country="gg">Guernsey</option>
                                        <option data-country="gn">Guinea</option>
                                        <option data-country="gw">Guinea-Bissau</option>
                                        <option data-country="gy">Guyana</option>
                                        <option data-country="ht">Haiti</option>
                                        <option data-country="hm">Heard & McDonald Islands</option>
                                        <option data-country="va">Vatican City</option>
                                        <option data-country="hn">Honduras</option>
                                        <option data-country="hk">Hong Kong</option>
                                        <option data-country="hu">Hungary</option>
                                        <option data-country="is">Iceland</option>
                                        <option data-country="in">India</option>
                                        <option data-country="id">Indonesia</option>
                                        <option data-country="ir">Iran</option>
                                        <option data-country="iq">Iraq</option>
                                        <option data-country="ie">Ireland</option>
                                        <option data-country="im">Isle of Man</option>
                                        <option data-country="il">Israel</option>
                                        <option data-country="it">Italy</option>
                                        <option data-country="jm">Jamaica</option>
                                        <option data-country="jp">Japan</option>
                                        <option data-country="je">Jersey</option>
                                        <option data-country="jo">Jordan</option>
                                        <option data-country="kz">Kazakhstan</option>
                                        <option data-country="ke">Kenya</option>
                                        <option data-country="ki">Kiribati</option>
                                        <option data-country="kp">North Korea</option>
                                        <option data-country="kr">South Korea</option>
                                        <option data-country="xk">Kosovo</option>
                                        <option data-country="kw">Kuwait</option>
                                        <option data-country="kg">Kyrgyzstan</option>
                                        <option data-country="la">Laos</option>
                                        <option data-country="lv">Latvia</option>
                                        <option data-country="lb">Lebanon</option>
                                        <option data-country="ls">Lesotho</option>
                                        <option data-country="lr">Liberia</option>
                                        <option data-country="ly">Libya</option>
                                        <option data-country="li">Liechtenstein</option>
                                        <option data-country="lt">Lithuania</option>
                                        <option data-country="lu">Luxembourg</option>
                                        <option data-country="mo">Macao</option>
                                        <option data-country="mk">North Macedonia</option>
                                        <option data-country="mg">Madagascar</option>
                                        <option data-country="mw">Malawi</option>
                                        <option data-country="my">Malaysia</option>
                                        <option data-country="mv">Maldives</option>
                                        <option data-country="ml">Mali</option>
                                        <option data-country="mt">Malta</option>
                                        <option data-country="mh">Marshall Islands</option>
                                        <option data-country="mq">Martinique</option>
                                        <option data-country="mr">Mauritania</option>
                                        <option data-country="mu">Mauritius</option>
                                        <option data-country="yt">Mayotte</option>
                                        <option data-country="mx">Mexico</option>
                                        <option data-country="fm">Micronesia</option>
                                        <option data-country="md">Moldova</option>
                                        <option data-country="mc">Monaco</option>
                                        <option data-country="mn">Mongolia</option>
                                        <option data-country="me">Montenegro</option>
                                        <option data-country="ms">Montserrat</option>
                                        <option data-country="ma">Morocco</option>
                                        <option data-country="mz">Mozambique</option>
                                        <option data-country="mm">Myanmar (Burma)</option>
                                        <option data-country="na">Namibia</option>
                                        <option data-country="nr">Nauru</option>
                                        <option data-country="np">Nepal</option>
                                        <option data-country="nl">Netherlands</option>
                                        <option data-country="cu">CuraÃ§ao</option>
                                        <option data-country="nc">New Caledonia</option>
                                        <option data-country="nz">New Zealand</option>
                                        <option data-country="ni">Nicaragua</option>
                                        <option data-country="ne">Niger</option>
                                        <option data-country="ng">Nigeria</option>
                                        <option data-country="nu">Niue</option>
                                        <option data-country="nf">Norfolk Island</option>
                                        <option data-country="mp">Northern Mariana Islands</option>
                                        <option data-country="no">Norway</option>
                                        <option data-country="om">Oman</option>
                                        <option data-country="pk">Pakistan</option>
                                        <option data-country="pw">Palau</option>
                                        <option data-country="ps">Palestine</option>
                                        <option data-country="pa">Panama</option>
                                        <option data-country="pg">Papua New Guinea</option>
                                        <option data-country="py">Paraguay</option>
                                        <option data-country="pe">Peru</option>
                                        <option data-country="ph">Philippines</option>
                                        <option data-country="pn">Pitcairn Islands</option>
                                        <option data-country="pl">Poland</option>
                                        <option data-country="pt">Portugal</option>
                                        <option data-country="pr">Puerto Rico</option>
                                        <option data-country="qa">Qatar</option>
                                        <option data-country="re">RÃ©union</option>
                                        <option data-country="ro">Romania</option>
                                        <option data-country="ru">Russia</option>
                                        <option data-country="rw">Rwanda</option>
                                        <option data-country="bl">St. BarthÃ©lemy</option>
                                        <option data-country="sh">St. Helena</option>
                                        <option data-country="kn">St. Kitts & Nevis</option>
                                        <option data-country="lc">St. Lucia</option>
                                        <option data-country="mf">St. Martin</option>
                                        <option data-country="pm">St. Pierre & Miquelon</option>
                                        <option data-country="vc">St. Vincent & Grenadines</option>
                                        <option data-country="ws">Samoa</option>
                                        <option data-country="sm">San Marino</option>
                                        <option data-country="st">SÃ£o TomÃ© & PrÃ­ncipe</option>
                                        <option data-country="sa">Saudi Arabia</option>
                                        <option data-country="sn">Senegal</option>
                                        <option data-country="rs">Serbia</option>
                                        <option data-country="sr">Serbia</option>
                                        <option data-country="sc">Seychelles</option>
                                        <option data-country="sl">Sierra Leone</option>
                                        <option data-country="sg">Singapore</option>
                                        <option data-country="sx">Sint Maarten</option>
                                        <option data-country="sk">Slovakia</option>
                                        <option data-country="si">Slovenia</option>
                                        <option data-country="sb">Solomon Islands</option>
                                        <option data-country="so">Somalia</option>
                                        <option data-country="za">South Africa</option>
                                        <option data-country="gs">South Georgia & South Sandwich Islands</option>
                                        <option data-country="ss">South Sudan</option>
                                        <option data-country="es">Spain</option>
                                        <option data-country="lk">Sri Lanka</option>
                                        <option data-country="sd">Sudan</option>
                                        <option data-country="sr">Suriname</option>
                                        <option data-country="sj">Svalbard & Jan Mayen</option>
                                        <option data-country="sz">Eswatini</option>
                                        <option data-country="se">Sweden</option>
                                        <option data-country="ch">Switzerland</option>
                                        <option data-country="sy">Syria</option>
                                        <option data-country="tw">Taiwan</option>
                                        <option data-country="tj">Tajikistan</option>
                                        <option data-country="tz">Tanzania</option>
                                        <option data-country="th">Thailand</option>
                                        <option data-country="tl">Timor-Leste</option>
                                        <option data-country="tg">Togo</option>
                                        <option data-country="tk">Tokelau</option>
                                        <option data-country="to">Tonga</option>
                                        <option data-country="tt">Trinidad & Tobago</option>
                                        <option data-country="tn">Tunisia</option>
                                        <option data-country="tr">Turkey</option>
                                        <option data-country="tm">Turkmenistan</option>
                                        <option data-country="tc">Turks & Caicos Islands</option>
                                        <option data-country="tv">Tuvalu</option>
                                        <option data-country="ug">Uganda</option>
                                        <option data-country="ua">Ukraine</option>
                                        <option data-country="ae">United Arab Emirates</option>
                                        <option data-country="gb">United Kingdom</option>
                                        <option data-country="us" selected>United States</option>
                                        <option data-country="um">U.S. Outlying Islands</option>
                                        <option data-country="uy">Uruguay</option>
                                        <option data-country="uz">Uzbekistan</option>
                                        <option data-country="vu">Vanuatu</option>
                                        <option data-country="ve">Venezuela</option>
                                        <option data-country="vn">Vietnam</option>
                                        <option data-country="vg">British Virgin Islands</option>
                                        <option data-country="vi">U.S. Virgin Islands</option>
                                        <option data-country="wf">Wallis & Futuna</option>
                                        <option data-country="eh">Western Sahara</option>
                                        <option data-country="ye">Yemen</option>
                                        <option data-country="zm">Zambia</option>
                                        <option data-country="zw">Zimbabwe</option>
                                    </select>
                                    <small class="form-text text-muted">Default Country [Ex: United State/United Kingdom]</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ Footer ] start -->
                    <footer class="footer">
                        <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                            <span>Copyright ï¿½</span>
                            <script>
                                document.write(new Date().getFullYear());
                            </script>
                        </p>
                        <div class="d-flex align-items-center gap-4">
                            <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                            <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                            <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
                        </div>
                    </footer>
                    <!-- [ Footer ] end -->
                </div>
                <!-- [ Content Area ] end -->
            </div>
            <?php
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php'; ?>


