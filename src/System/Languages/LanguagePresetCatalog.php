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
            new LanguagePreset('aa', 'Afar', 'Afar'),
            new LanguagePreset('ab', 'Abkhazian', 'Abkhazian'),
            new LanguagePreset('ae', 'Avestan', 'Avestan'),
            new LanguagePreset('af', 'Afrikaans', 'Afrikaans'),
            new LanguagePreset('ak', 'Akan', 'Akan'),
            new LanguagePreset('am', 'Amharic', 'አማርኛ'),
            new LanguagePreset('an', 'Aragonese', 'Aragonese'),
            new LanguagePreset('ar', 'Arabic', 'العربية'),
            new LanguagePreset('as', 'Assamese', 'অসমীয়া'),
            new LanguagePreset('av', 'Avaric', 'Avaric'),
            new LanguagePreset('ay', 'Aymara', 'Aymara'),
            new LanguagePreset('az', 'Azerbaijani', 'azərbaycan'),
            new LanguagePreset('ba', 'Bashkir', 'Bashkir'),
            new LanguagePreset('be', 'Belarusian', 'беларуская'),
            new LanguagePreset('bg', 'Bulgarian', 'български'),
            new LanguagePreset('bh', 'Bihari languages', 'Bhojpuri'),
            new LanguagePreset('bi', 'Bislama', 'Bislama'),
            new LanguagePreset('bm', 'Bambara', 'bamanakan'),
            new LanguagePreset('bn', 'Bengali', 'বাংলা'),
            new LanguagePreset('bo', 'Tibetan', 'བོད་སྐད་'),
            new LanguagePreset('br', 'Breton', 'brezhoneg'),
            new LanguagePreset('bs', 'Bosnian', 'bosanski'),
            new LanguagePreset('ca', 'Catalan; Valencian', 'català'),
            new LanguagePreset('ce', 'Chechen', 'нохчийн'),
            new LanguagePreset('ch', 'Chamorro', 'Chamorro'),
            new LanguagePreset('co', 'Corsican', 'Corsican'),
            new LanguagePreset('cr', 'Cree', 'Cree'),
            new LanguagePreset('cs', 'Czech', 'čeština'),
            new LanguagePreset('cu', 'Church Slavic', 'Church Slavic'),
            new LanguagePreset('cv', 'Chuvash', 'чӑваш'),
            new LanguagePreset('cy', 'Welsh', 'Cymraeg'),
            new LanguagePreset('da', 'Danish', 'dansk'),
            new LanguagePreset('de', 'German', 'Deutsch'),
            new LanguagePreset('dv', 'Divehi', 'Divehi'),
            new LanguagePreset('dz', 'Dzongkha', 'རྫོང་ཁ'),
            new LanguagePreset('ee', 'Ewe', 'Eʋegbe'),
            new LanguagePreset('el', 'Greek', 'Ελληνικά'),
            new LanguagePreset('en', 'English', 'English'),
            new LanguagePreset('eo', 'Esperanto', 'Esperanto'),
            new LanguagePreset('es', 'Spanish', 'español'),
            new LanguagePreset('et', 'Estonian', 'eesti'),
            new LanguagePreset('eu', 'Basque', 'euskara'),
            new LanguagePreset('fa', 'Persian', 'فارسی'),
            new LanguagePreset('ff', 'Fulah', 'Pulaar'),
            new LanguagePreset('fi', 'Finnish', 'suomi'),
            new LanguagePreset('fj', 'Fijian', 'Fijian'),
            new LanguagePreset('fo', 'Faroese', 'føroyskt'),
            new LanguagePreset('fr', 'French', 'français'),
            new LanguagePreset('fy', 'Western Frisian', 'Frysk'),
            new LanguagePreset('ga', 'Irish', 'Gaeilge'),
            new LanguagePreset('gd', 'Scottish Gaelic', 'Gàidhlig'),
            new LanguagePreset('gl', 'Galician', 'galego'),
            new LanguagePreset('gn', 'Guarani', 'Guarani'),
            new LanguagePreset('gu', 'Gujarati', 'ગુજરાતી'),
            new LanguagePreset('gv', 'Manx', 'Gaelg'),
            new LanguagePreset('ha', 'Hausa', 'Hausa'),
            new LanguagePreset('he', 'Hebrew', 'עברית'),
            new LanguagePreset('hi', 'Hindi', 'हिन्दी'),
            new LanguagePreset('ho', 'Hiri Motu', 'Hiri Motu'),
            new LanguagePreset('hr', 'Croatian', 'hrvatski'),
            new LanguagePreset('ht', 'Haitian Creole', 'créole haïtien'),
            new LanguagePreset('hu', 'Hungarian', 'magyar'),
            new LanguagePreset('hy', 'Armenian', 'հայերեն'),
            new LanguagePreset('hz', 'Herero', 'Herero'),
            new LanguagePreset('ia', 'Interlingua', 'interlingua'),
            new LanguagePreset('id', 'Indonesian', 'Indonesia'),
            new LanguagePreset('ie', 'Interlingue', 'Interlingue'),
            new LanguagePreset('ig', 'Igbo', 'Igbo'),
            new LanguagePreset('ii', 'Sichuan Yi', 'ꆈꌠꉙ'),
            new LanguagePreset('ik', 'Inupiaq', 'Inupiaq'),
            new LanguagePreset('io', 'Ido', 'Ido'),
            new LanguagePreset('is', 'Icelandic', 'íslenska'),
            new LanguagePreset('it', 'Italian', 'italiano'),
            new LanguagePreset('iu', 'Inuktitut', 'Inuktitut'),
            new LanguagePreset('ja', 'Japanese', '日本語'),
            new LanguagePreset('jv', 'Javanese', 'Jawa'),
            new LanguagePreset('ka', 'Georgian', 'ქართული'),
            new LanguagePreset('kg', 'Kongo', 'Kongo'),
            new LanguagePreset('ki', 'Kikuyu', 'Gikuyu'),
            new LanguagePreset('kj', 'Kuanyama', 'Kuanyama'),
            new LanguagePreset('kk', 'Kazakh', 'қазақ тілі'),
            new LanguagePreset('kl', 'Kalaallisut', 'kalaallisut'),
            new LanguagePreset('km', 'Central Khmer', 'ខ្មែរ'),
            new LanguagePreset('kn', 'Kannada', 'ಕನ್ನಡ'),
            new LanguagePreset('ko', 'Korean', '한국어'),
            new LanguagePreset('kr', 'Kanuri', 'Kanuri'),
            new LanguagePreset('ks', 'Kashmiri', 'کٲشُر'),
            new LanguagePreset('ku', 'Kurdish', 'kurdî (kurmancî)'),
            new LanguagePreset('kv', 'Komi', 'Komi'),
            new LanguagePreset('kw', 'Cornish', 'kernewek'),
            new LanguagePreset('ky', 'Kyrgyz', 'кыргызча'),
            new LanguagePreset('la', 'Latin', 'Latin'),
            new LanguagePreset('lb', 'Luxembourgish', 'Lëtzebuergesch'),
            new LanguagePreset('lg', 'Ganda', 'Luganda'),
            new LanguagePreset('li', 'Limburgish', 'Limburgish'),
            new LanguagePreset('ln', 'Lingala', 'lingála'),
            new LanguagePreset('lo', 'Lao', 'ລາວ'),
            new LanguagePreset('lt', 'Lithuanian', 'lietuvių'),
            new LanguagePreset('lu', 'Luba-Katanga', 'Tshiluba'),
            new LanguagePreset('lv', 'Latvian', 'latviešu'),
            new LanguagePreset('mg', 'Malagasy', 'Malagasy'),
            new LanguagePreset('mh', 'Marshallese', 'Marshallese'),
            new LanguagePreset('mi', 'Māori', 'Māori'),
            new LanguagePreset('mk', 'Macedonian', 'македонски'),
            new LanguagePreset('ml', 'Malayalam', 'മലയാളം'),
            new LanguagePreset('mn', 'Mongolian', 'монгол'),
            new LanguagePreset('mr', 'Marathi', 'मराठी'),
            new LanguagePreset('ms', 'Malay', 'Melayu'),
            new LanguagePreset('mt', 'Maltese', 'Malti'),
            new LanguagePreset('my', 'Burmese', 'မြန်မာ'),
            new LanguagePreset('na', 'Nauru', 'Nauru'),
            new LanguagePreset('nb', 'Norwegian Bokmål', 'norsk bokmål'),
            new LanguagePreset('nd', 'North Ndebele', 'isiNdebele'),
            new LanguagePreset('ne', 'Nepali', 'नेपाली'),
            new LanguagePreset('ng', 'Ndonga', 'Ndonga'),
            new LanguagePreset('nl', 'Dutch', 'Nederlands'),
            new LanguagePreset('nn', 'Norwegian Nynorsk', 'norsk nynorsk'),
            new LanguagePreset('no', 'Norwegian', 'norsk'),
            new LanguagePreset('nr', 'South Ndebele', 'South Ndebele'),
            new LanguagePreset('nv', 'Navajo', 'Navajo'),
            new LanguagePreset('ny', 'Nyanja', 'Nyanja'),
            new LanguagePreset('oc', 'Occitan', 'occitan'),
            new LanguagePreset('oj', 'Ojibwa', 'Ojibwa'),
            new LanguagePreset('om', 'Oromo', 'Oromoo'),
            new LanguagePreset('or', 'Odia', 'ଓଡ଼ିଆ'),
            new LanguagePreset('os', 'Ossetic', 'ирон'),
            new LanguagePreset('pa', 'Punjabi', 'ਪੰਜਾਬੀ'),
            new LanguagePreset('pi', 'Pali', 'Pali'),
            new LanguagePreset('pl', 'Polish', 'polski'),
            new LanguagePreset('ps', 'Pashto', 'پښتو'),
            new LanguagePreset('pt', 'Portuguese', 'português'),
            new LanguagePreset('qu', 'Quechua', 'Runasimi'),
            new LanguagePreset('rm', 'Romansh', 'rumantsch'),
            new LanguagePreset('rn', 'Rundi', 'Ikirundi'),
            new LanguagePreset('ro', 'Romanian', 'română'),
            new LanguagePreset('ru', 'Russian', 'русский'),
            new LanguagePreset('rw', 'Kinyarwanda', 'Kinyarwanda'),
            new LanguagePreset('sa', 'Sanskrit', 'संस्कृत भाषा'),
            new LanguagePreset('sc', 'Sardinian', 'sardu'),
            new LanguagePreset('sd', 'Sindhi', 'سنڌي'),
            new LanguagePreset('se', 'Northern Sami', 'davvisámegiella'),
            new LanguagePreset('sg', 'Sango', 'Sängö'),
            new LanguagePreset('si', 'Sinhala', 'සිංහල'),
            new LanguagePreset('sk', 'Slovak', 'slovenčina'),
            new LanguagePreset('sl', 'Slovenian', 'slovenščina'),
            new LanguagePreset('sm', 'Samoan', 'Samoan'),
            new LanguagePreset('sn', 'Shona', 'chiShona'),
            new LanguagePreset('so', 'Somali', 'Soomaali'),
            new LanguagePreset('sq', 'Albanian', 'shqip'),
            new LanguagePreset('sr', 'Serbian', 'српски'),
            new LanguagePreset('ss', 'Swati', 'Swati'),
            new LanguagePreset('st', 'Southern Sotho', 'Southern Sotho'),
            new LanguagePreset('su', 'Sundanese', 'Basa Sunda'),
            new LanguagePreset('sv', 'Swedish', 'svenska'),
            new LanguagePreset('sw', 'Swahili', 'Kiswahili'),
            new LanguagePreset('ta', 'Tamil', 'தமிழ்'),
            new LanguagePreset('te', 'Telugu', 'తెలుగు'),
            new LanguagePreset('tg', 'Tajik', 'тоҷикӣ'),
            new LanguagePreset('th', 'Thai', 'ไทย'),
            new LanguagePreset('ti', 'Tigrinya', 'ትግርኛ'),
            new LanguagePreset('tk', 'Turkmen', 'türkmen dili'),
            new LanguagePreset('tl', 'Tagalog', 'Tagalog'),
            new LanguagePreset('tn', 'Tswana', 'Tswana'),
            new LanguagePreset('to', 'Tongan', 'lea fakatonga'),
            new LanguagePreset('tr', 'Turkish', 'Türkçe'),
            new LanguagePreset('ts', 'Tsonga', 'Tsonga'),
            new LanguagePreset('tt', 'Tatar', 'татар'),
            new LanguagePreset('tw', 'Twi', 'Twi'),
            new LanguagePreset('ty', 'Tahitian', 'Tahitian'),
            new LanguagePreset('ug', 'Uyghur', 'ئۇيغۇرچە'),
            new LanguagePreset('uk', 'Ukrainian', 'українська'),
            new LanguagePreset('ur', 'Urdu', 'اردو'),
            new LanguagePreset('uz', 'Uzbek', 'o‘zbek'),
            new LanguagePreset('ve', 'Venda', 'Venda'),
            new LanguagePreset('vi', 'Vietnamese', 'Tiếng Việt'),
            new LanguagePreset('vo', 'Volapük', 'Volapük'),
            new LanguagePreset('wa', 'Walloon', 'Walloon'),
            new LanguagePreset('wo', 'Wolof', 'Wolof'),
            new LanguagePreset('xh', 'Xhosa', 'IsiXhosa'),
            new LanguagePreset('yi', 'Yiddish', 'ייִדיש'),
            new LanguagePreset('yo', 'Yoruba', 'Èdè Yorùbá'),
            new LanguagePreset('za', 'Zhuang', 'Vahcuengh'),
            new LanguagePreset('zh', 'Chinese', '中文'),
            new LanguagePreset('zu', 'Zulu', 'isiZulu'),
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
     * @return array<string, array{name:string}>
     */
    public static function editorValues(): array
    {
        $values = [];

        foreach (self::all() as $preset) {
            $values[$preset->code()] = ['name' => $preset->nativeName()];
        }

        return $values;
    }
}
