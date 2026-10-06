<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages;

/**
 * Poskytuje staticka metadata canonicalnich ISO 639-1 jazykovych presetu
 */
final class LanguagePresetCatalog
{
    /**
     * Vraci vsechny canonicalni jazykove presety serazene podle ISO kodu
     *
     * @return list<LanguagePreset>
     */
    public static function all(): array
    {
        return [
            new LanguagePreset('aa', 'Afar', 'Afar', 'DJ'),
            new LanguagePreset('ab', 'Abkhazian', 'Abkhazian', 'GE'),
            new LanguagePreset('ae', 'Avestan', 'Avestan', null),
            new LanguagePreset('af', 'Afrikaans', 'Afrikaans', 'ZA'),
            new LanguagePreset('ak', 'Akan', 'Akan', 'GH'),
            new LanguagePreset('am', 'Amharic', 'አማርኛ', 'ET'),
            new LanguagePreset('an', 'Aragonese', 'Aragonese', 'ES'),
            new LanguagePreset('ar', 'Arabic', 'العربية', null),
            new LanguagePreset('as', 'Assamese', 'অসমীয়া', 'IN'),
            new LanguagePreset('av', 'Avaric', 'Avaric', 'RU'),
            new LanguagePreset('ay', 'Aymara', 'Aymara', 'BO'),
            new LanguagePreset('az', 'Azerbaijani', 'azərbaycan', 'AZ'),
            new LanguagePreset('ba', 'Bashkir', 'Bashkir', 'RU'),
            new LanguagePreset('be', 'Belarusian', 'беларуская', 'BY'),
            new LanguagePreset('bg', 'Bulgarian', 'български', 'BG'),
            new LanguagePreset('bh', 'Bihari languages', 'Bhojpuri', 'IN'),
            new LanguagePreset('bi', 'Bislama', 'Bislama', 'VU'),
            new LanguagePreset('bm', 'Bambara', 'bamanakan', 'ML'),
            new LanguagePreset('bn', 'Bengali', 'বাংলা', 'BD'),
            new LanguagePreset('bo', 'Tibetan', 'བོད་སྐད་', 'CN'),
            new LanguagePreset('br', 'Breton', 'brezhoneg', 'FR'),
            new LanguagePreset('bs', 'Bosnian', 'bosanski', 'BA'),
            new LanguagePreset('ca', 'Catalan; Valencian', 'català', 'ES'),
            new LanguagePreset('ce', 'Chechen', 'нохчийн', 'RU'),
            new LanguagePreset('ch', 'Chamorro', 'Chamorro', 'GU'),
            new LanguagePreset('co', 'Corsican', 'Corsican', 'FR'),
            new LanguagePreset('cr', 'Cree', 'Cree', 'CA'),
            new LanguagePreset('cs', 'Czech', 'čeština', 'CZ'),
            new LanguagePreset('cu', 'Church Slavic', 'Church Slavic', 'BG'),
            new LanguagePreset('cv', 'Chuvash', 'чӑваш', 'RU'),
            new LanguagePreset('cy', 'Welsh', 'Cymraeg', 'GB'),
            new LanguagePreset('da', 'Danish', 'dansk', 'DK'),
            new LanguagePreset('de', 'German', 'Deutsch', 'DE'),
            new LanguagePreset('dv', 'Divehi', 'Divehi', 'MV'),
            new LanguagePreset('dz', 'Dzongkha', 'རྫོང་ཁ', 'BT'),
            new LanguagePreset('ee', 'Ewe', 'Eʋegbe', 'GH'),
            new LanguagePreset('el', 'Greek', 'Ελληνικά', 'GR'),
            new LanguagePreset('en', 'English', 'English', 'GB'),
            new LanguagePreset('eo', 'Esperanto', 'Esperanto', null),
            new LanguagePreset('es', 'Spanish', 'español', 'ES'),
            new LanguagePreset('et', 'Estonian', 'eesti', 'EE'),
            new LanguagePreset('eu', 'Basque', 'euskara', 'ES'),
            new LanguagePreset('fa', 'Persian', 'فارسی', 'IR'),
            new LanguagePreset('ff', 'Fulah', 'Pulaar', null),
            new LanguagePreset('fi', 'Finnish', 'suomi', 'FI'),
            new LanguagePreset('fj', 'Fijian', 'Fijian', 'FJ'),
            new LanguagePreset('fo', 'Faroese', 'føroyskt', 'FO'),
            new LanguagePreset('fr', 'French', 'français', 'FR'),
            new LanguagePreset('fy', 'Western Frisian', 'Frysk', 'NL'),
            new LanguagePreset('ga', 'Irish', 'Gaeilge', 'IE'),
            new LanguagePreset('gd', 'Scottish Gaelic', 'Gàidhlig', 'GB'),
            new LanguagePreset('gl', 'Galician', 'galego', 'ES'),
            new LanguagePreset('gn', 'Guarani', 'Guarani', 'PY'),
            new LanguagePreset('gu', 'Gujarati', 'ગુજરાતી', 'IN'),
            new LanguagePreset('gv', 'Manx', 'Gaelg', 'IM'),
            new LanguagePreset('ha', 'Hausa', 'Hausa', 'NG'),
            new LanguagePreset('he', 'Hebrew', 'עברית', 'IL'),
            new LanguagePreset('hi', 'Hindi', 'हिन्दी', 'IN'),
            new LanguagePreset('ho', 'Hiri Motu', 'Hiri Motu', 'PG'),
            new LanguagePreset('hr', 'Croatian', 'hrvatski', 'HR'),
            new LanguagePreset('ht', 'Haitian Creole', 'créole haïtien', 'HT'),
            new LanguagePreset('hu', 'Hungarian', 'magyar', 'HU'),
            new LanguagePreset('hy', 'Armenian', 'հայերեն', 'AM'),
            new LanguagePreset('hz', 'Herero', 'Herero', 'NA'),
            new LanguagePreset('ia', 'Interlingua', 'interlingua', null),
            new LanguagePreset('id', 'Indonesian', 'Indonesia', 'ID'),
            new LanguagePreset('ie', 'Interlingue', 'Interlingue', null),
            new LanguagePreset('ig', 'Igbo', 'Igbo', 'NG'),
            new LanguagePreset('ii', 'Sichuan Yi', 'ꆈꌠꉙ', 'CN'),
            new LanguagePreset('ik', 'Inupiaq', 'Inupiaq', 'CA'),
            new LanguagePreset('io', 'Ido', 'Ido', null),
            new LanguagePreset('is', 'Icelandic', 'íslenska', 'IS'),
            new LanguagePreset('it', 'Italian', 'italiano', 'IT'),
            new LanguagePreset('iu', 'Inuktitut', 'Inuktitut', 'CA'),
            new LanguagePreset('ja', 'Japanese', '日本語', 'JP'),
            new LanguagePreset('jv', 'Javanese', 'Jawa', 'ID'),
            new LanguagePreset('ka', 'Georgian', 'ქართული', 'GE'),
            new LanguagePreset('kg', 'Kongo', 'Kongo', 'CG'),
            new LanguagePreset('ki', 'Kikuyu', 'Gikuyu', 'KE'),
            new LanguagePreset('kj', 'Kuanyama', 'Kuanyama', 'AO'),
            new LanguagePreset('kk', 'Kazakh', 'қазақ тілі', 'KZ'),
            new LanguagePreset('kl', 'Kalaallisut', 'kalaallisut', 'GL'),
            new LanguagePreset('km', 'Central Khmer', 'ខ្មែរ', 'KH'),
            new LanguagePreset('kn', 'Kannada', 'ಕನ್ನಡ', 'IN'),
            new LanguagePreset('ko', 'Korean', '한국어', 'KR'),
            new LanguagePreset('kr', 'Kanuri', 'Kanuri', 'NG'),
            new LanguagePreset('ks', 'Kashmiri', 'کٲشُر', 'IN'),
            new LanguagePreset('ku', 'Kurdish', 'kurdî (kurmancî)', null),
            new LanguagePreset('kv', 'Komi', 'Komi', 'RU'),
            new LanguagePreset('kw', 'Cornish', 'kernewek', 'GB'),
            new LanguagePreset('ky', 'Kyrgyz', 'кыргызча', 'KG'),
            new LanguagePreset('la', 'Latin', 'Latin', null),
            new LanguagePreset('lb', 'Luxembourgish', 'Lëtzebuergesch', 'LU'),
            new LanguagePreset('lg', 'Ganda', 'Luganda', 'UG'),
            new LanguagePreset('li', 'Limburgish', 'Limburgish', 'NL'),
            new LanguagePreset('ln', 'Lingala', 'lingála', 'CD'),
            new LanguagePreset('lo', 'Lao', 'ລາວ', 'LA'),
            new LanguagePreset('lt', 'Lithuanian', 'lietuvių', 'LT'),
            new LanguagePreset('lu', 'Luba-Katanga', 'Tshiluba', 'CD'),
            new LanguagePreset('lv', 'Latvian', 'latviešu', 'LV'),
            new LanguagePreset('mg', 'Malagasy', 'Malagasy', 'MG'),
            new LanguagePreset('mh', 'Marshallese', 'Marshallese', 'MH'),
            new LanguagePreset('mi', 'Māori', 'Māori', 'NZ'),
            new LanguagePreset('mk', 'Macedonian', 'македонски', 'MK'),
            new LanguagePreset('ml', 'Malayalam', 'മലയാളം', 'IN'),
            new LanguagePreset('mn', 'Mongolian', 'монгол', 'MN'),
            new LanguagePreset('mr', 'Marathi', 'मराठी', 'IN'),
            new LanguagePreset('ms', 'Malay', 'Melayu', 'MY'),
            new LanguagePreset('mt', 'Maltese', 'Malti', 'MT'),
            new LanguagePreset('my', 'Burmese', 'မြန်မာ', 'MM'),
            new LanguagePreset('na', 'Nauru', 'Nauru', 'NR'),
            new LanguagePreset('nb', 'Norwegian Bokmål', 'norsk bokmål', 'NO'),
            new LanguagePreset('nd', 'North Ndebele', 'isiNdebele', 'ZW'),
            new LanguagePreset('ne', 'Nepali', 'नेपाली', 'NP'),
            new LanguagePreset('ng', 'Ndonga', 'Ndonga', 'NA'),
            new LanguagePreset('nl', 'Dutch', 'Nederlands', 'NL'),
            new LanguagePreset('nn', 'Norwegian Nynorsk', 'norsk nynorsk', 'NO'),
            new LanguagePreset('no', 'Norwegian', 'norsk', 'NO'),
            new LanguagePreset('nr', 'South Ndebele', 'South Ndebele', 'ZA'),
            new LanguagePreset('nv', 'Navajo', 'Navajo', 'US'),
            new LanguagePreset('ny', 'Nyanja', 'Nyanja', 'MW'),
            new LanguagePreset('oc', 'Occitan', 'occitan', 'FR'),
            new LanguagePreset('oj', 'Ojibwa', 'Ojibwa', 'CA'),
            new LanguagePreset('om', 'Oromo', 'Oromoo', 'ET'),
            new LanguagePreset('or', 'Odia', 'ଓଡ଼ିଆ', 'IN'),
            new LanguagePreset('os', 'Ossetic', 'ирон', null),
            new LanguagePreset('pa', 'Punjabi', 'ਪੰਜਾਬੀ', 'IN'),
            new LanguagePreset('pi', 'Pali', 'Pali', 'IN'),
            new LanguagePreset('pl', 'Polish', 'polski', 'PL'),
            new LanguagePreset('ps', 'Pashto', 'پښتو', 'AF'),
            new LanguagePreset('pt', 'Portuguese', 'português', 'PT'),
            new LanguagePreset('qu', 'Quechua', 'Runasimi', 'PE'),
            new LanguagePreset('rm', 'Romansh', 'rumantsch', 'CH'),
            new LanguagePreset('rn', 'Rundi', 'Ikirundi', 'BI'),
            new LanguagePreset('ro', 'Romanian', 'română', 'RO'),
            new LanguagePreset('ru', 'Russian', 'русский', 'RU'),
            new LanguagePreset('rw', 'Kinyarwanda', 'Kinyarwanda', 'RW'),
            new LanguagePreset('sa', 'Sanskrit', 'संस्कृत भाषा', 'IN'),
            new LanguagePreset('sc', 'Sardinian', 'sardu', 'IT'),
            new LanguagePreset('sd', 'Sindhi', 'سنڌي', 'PK'),
            new LanguagePreset('se', 'Northern Sami', 'davvisámegiella', null),
            new LanguagePreset('sg', 'Sango', 'Sängö', 'CF'),
            new LanguagePreset('si', 'Sinhala', 'සිංහල', 'LK'),
            new LanguagePreset('sk', 'Slovak', 'slovenčina', 'SK'),
            new LanguagePreset('sl', 'Slovenian', 'slovenščina', 'SI'),
            new LanguagePreset('sm', 'Samoan', 'Samoan', 'WS'),
            new LanguagePreset('sn', 'Shona', 'chiShona', 'ZW'),
            new LanguagePreset('so', 'Somali', 'Soomaali', 'SO'),
            new LanguagePreset('sq', 'Albanian', 'shqip', 'AL'),
            new LanguagePreset('sr', 'Serbian', 'српски', 'RS'),
            new LanguagePreset('ss', 'Swati', 'Swati', 'SZ'),
            new LanguagePreset('st', 'Southern Sotho', 'Southern Sotho', 'LS'),
            new LanguagePreset('su', 'Sundanese', 'Basa Sunda', 'ID'),
            new LanguagePreset('sv', 'Swedish', 'svenska', 'SE'),
            new LanguagePreset('sw', 'Swahili', 'Kiswahili', 'TZ'),
            new LanguagePreset('ta', 'Tamil', 'தமிழ்', 'IN'),
            new LanguagePreset('te', 'Telugu', 'తెలుగు', 'IN'),
            new LanguagePreset('tg', 'Tajik', 'тоҷикӣ', 'TJ'),
            new LanguagePreset('th', 'Thai', 'ไทย', 'TH'),
            new LanguagePreset('ti', 'Tigrinya', 'ትግርኛ', 'ER'),
            new LanguagePreset('tk', 'Turkmen', 'türkmen dili', 'TM'),
            new LanguagePreset('tl', 'Tagalog', 'Tagalog', 'PH'),
            new LanguagePreset('tn', 'Tswana', 'Tswana', 'BW'),
            new LanguagePreset('to', 'Tongan', 'lea fakatonga', 'TO'),
            new LanguagePreset('tr', 'Turkish', 'Türkçe', 'TR'),
            new LanguagePreset('ts', 'Tsonga', 'Tsonga', 'ZA'),
            new LanguagePreset('tt', 'Tatar', 'татар', 'RU'),
            new LanguagePreset('tw', 'Twi', 'Twi', 'GH'),
            new LanguagePreset('ty', 'Tahitian', 'Tahitian', 'PF'),
            new LanguagePreset('ug', 'Uyghur', 'ئۇيغۇرچە', 'CN'),
            new LanguagePreset('uk', 'Ukrainian', 'українська', 'UA'),
            new LanguagePreset('ur', 'Urdu', 'اردو', 'PK'),
            new LanguagePreset('uz', 'Uzbek', 'o‘zbek', 'UZ'),
            new LanguagePreset('ve', 'Venda', 'Venda', 'ZA'),
            new LanguagePreset('vi', 'Vietnamese', 'Tiếng Việt', 'VN'),
            new LanguagePreset('vo', 'Volapük', 'Volapük', null),
            new LanguagePreset('wa', 'Walloon', 'Walloon', 'BE'),
            new LanguagePreset('wo', 'Wolof', 'Wolof', 'SN'),
            new LanguagePreset('xh', 'Xhosa', 'IsiXhosa', 'ZA'),
            new LanguagePreset('yi', 'Yiddish', 'ייִדיש', null),
            new LanguagePreset('yo', 'Yoruba', 'Èdè Yorùbá', 'NG'),
            new LanguagePreset('za', 'Zhuang', 'Vahcuengh', 'CN'),
            new LanguagePreset('zh', 'Chinese', '中文', null),
            new LanguagePreset('zu', 'Zulu', 'isiZulu', 'ZA'),
        ];
    }

    /**
     * Vytvari popisky pro vyber presetu v editoru
     *
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        $options = ['' => '—'];

        foreach (self::all() as $preset) {
            $options[$preset->code()] = sprintf('%s — %s', $preset->displayName(), $preset->nativeName());
        }

        return $options;
    }

    /**
     * Vytvari hodnoty, kterymi editor predvyplni vybrany preset
     *
     * @return array<string, array{name:string, flagCode:string|null}>
     */
    public static function editorValues(): array
    {
        $values = [];

        foreach (self::all() as $preset) {
            $values[$preset->code()] = [
                'name' => $preset->nativeName(),
                'flagCode' => $preset->flagCode(),
            ];
        }

        return $values;
    }
}
