CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE IF NOT EXISTS kinds (kind_key TEXT PRIMARY KEY, config_json TEXT);
CREATE TABLE IF NOT EXISTS translations (id INTEGER PRIMARY KEY AUTOINCREMENT, lang TEXT, phrase_key TEXT, phrase_value TEXT);
CREATE TABLE IF NOT EXISTS url_translations (id INTEGER PRIMARY KEY AUTOINCREMENT, lang TEXT, slug_key TEXT, slug_value TEXT);
CREATE TABLE IF NOT EXISTS webmentions (id INTEGER PRIMARY KEY AUTOINCREMENT, hash TEXT UNIQUE, payload_json TEXT);
CREATE TABLE IF NOT EXISTS microsub_channels (uid TEXT PRIMARY KEY, name TEXT);
CREATE TABLE IF NOT EXISTS microsub_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_uid TEXT, url TEXT, type TEXT, name TEXT, photo TEXT);
CREATE TABLE IF NOT EXISTS microsub_items (id TEXT PRIMARY KEY, channel_uid TEXT, url TEXT, content TEXT, published INTEGER, author_name TEXT, author_photo TEXT, is_read INTEGER);

INSERT OR IGNORE INTO microsub_channels (uid, name) VALUES ('inbox', 'Timeline');
INSERT OR IGNORE INTO microsub_channels (uid, name) VALUES ('notifications', 'Notifications');

INSERT OR REPLACE INTO settings (key, value) VALUES ('base', '/');
INSERT OR REPLACE INTO settings (key, value) VALUES ('title', 'Lumen Pink');
INSERT OR REPLACE INTO settings (key, value) VALUES ('sitename', 'Um pouco de cada e um monte de nada');
INSERT OR REPLACE INTO settings (key, value) VALUES ('fqdn', 'http://localhost:8081');
INSERT OR REPLACE INTO settings (key, value) VALUES ('author', '~lumen');
INSERT OR REPLACE INTO settings (key, value) VALUES ('buildall', '1');
INSERT OR REPLACE INTO settings (key, value) VALUES ('outputdir', 'public');
INSERT OR REPLACE INTO settings (key, value) VALUES ('contentdir', 'content');
INSERT OR REPLACE INTO settings (key, value) VALUES ('lang', '["en","pt","es"]');
INSERT OR REPLACE INTO settings (key, value) VALUES ('defaultlang', 'en');
INSERT OR REPLACE INTO settings (key, value) VALUES ('support', '["md","txt","html","htm"]');
INSERT OR REPLACE INTO settings (key, value) VALUES ('htmlpostprocessing', 'minify');
INSERT OR REPLACE INTO settings (key, value) VALUES ('prettylinks', '1');
INSERT OR REPLACE INTO settings (key, value) VALUES ('defaultcategory', 'General');
INSERT OR REPLACE INTO settings (key, value) VALUES ('dev', '');
INSERT OR REPLACE INTO settings (key, value) VALUES ('twtxt', '{"nick":"lumen","description":"","avatar":"","following":[],"hubs":["https:\/\/search.twtxt.net\/"]}');
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('note', '{"content_dir":"notes","title":{"pt":"Notas","en":"Notes","es":"Notas"},"palette":{"bg":"#E8EDE7","fg":"#2A3B2C"},"has_title":false,"show_on_home":true,"display_mode":"full_content"}');
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('photo', '{"content_dir":"photos","title":{"pt":"Fotos","en":"Photos","es":"Fotos"},"palette":{"bg":"#E6EDF2","fg":"#1C3A5A"},"has_title":false,"show_on_home":true,"display_mode":"thumbnail_snippet"}');
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('article', '{"content_dir":"articles","title":{"pt":"Artigos","en":"Articles","es":"Artículos"},"palette":{"bg":"#FDF6E3","fg":"#3A2E2A"},"has_title":true,"show_on_home":true,"display_mode":"default"}');
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('garden', '{"content_dir":"garden","title":{"pt":"Jardim","en":"Garden","es":"Jardín"},"palette":{"bg":"#F0EAE1","fg":"#5C3A21"},"has_title":true,"show_on_home":false,"display_mode":"default"}');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Home', 'Início');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Home', 'Home');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Home', 'Inicio');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Index', 'Índice');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Index', 'Index');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Index', 'Índice');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Now', 'Agora');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Now', 'Now');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Now', 'Ahora');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'now', 'agora');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'now', 'now');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'now', 'ahora');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'about-the-blog', 'sobre-o-blog');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'about-the-blog', 'about-the-blog');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'about-the-blog', 'sobre-el-blog');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Recent posts', 'Publicações recentes');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Recent posts', 'Recent posts');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Recent posts', 'Publicaciones recientes');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Browse the sections of the site in Gopher style:', 'Navegue pelas seções do site no estilo Gopher:');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Browse the sections of the site in Gopher style:', 'Browse the sections of the site in Gopher style:');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Browse the sections of the site in Gopher style:', 'Navegue por las secciones del sitio en estilo Gopher:');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Articles', 'Artigos');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Articles', 'Articles');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Articles', 'Artículos');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Notes', 'Notas');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Notes', 'Notes');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Notes', 'Notas');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Photos', 'Fotos');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Photos', 'Photos');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Photos', 'Fotos');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Garden', 'Jardim');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Garden', 'Garden');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Garden', 'Jardín');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'About', 'Sobre');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'About', 'About');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'About', 'Sobre');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Maturity', 'Maturidade');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Maturity', 'Maturity');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Maturity', 'Madurez');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('pt', 'Reliability', 'Confiabilidade');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Reliability', 'Reliability');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('es', 'Reliability', 'Confiabilidad');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'agora', 'now');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'agora', 'ahora');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'cronicas', 'chronicles');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'cronicas', 'cronicas');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'projetos', 'projects');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'projetos', 'proyectos');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'sobre-o-blog', 'about-the-blog');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'sobre-o-blog', 'sobre-el-blog');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'aibo', 'partner');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'aibo', 'aibo');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'automacao-da-transvette-com-mycroft', 'automation-of-transvette-with-mycroft');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'automacao-da-transvette-com-mycroft', 'automatizacion-de-transvette-con-mycroft-');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'automacao-residencial-com-mycroft', 'home-automation-with-mycroft');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'automacao-residencial-com-mycroft', 'automatizacion-residencial-con-mycroft');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'cosplay-de-furiosa', 'furiosa-cosplay');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'cosplay-de-furiosa', 'cosplay-de-furiosa');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'hud-para-capacete-de-moto', 'motorcycle-helmet-hud');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'hud-para-capacete-de-moto', 'hud-para-casco-de-moto');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'capacetes-tematicos', 'themed-helmets');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'capacetes-tematicos', 'cascos-tematicos');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'hud-para-carro', 'car-hud--head-up-display-');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'hud-para-carro', 'hud-para-automovil');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'maroomba', 'my-roomba');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'maroomba', 'mi-roomba');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'transcom-para-motos-com-bluetooth-e-px', 'bluetooth-and-cb-radio-communication-system-for-motorcycles');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'transcom-para-motos-com-bluetooth-e-px', 'transcom-para-motos-con-bluetooth-y-px');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'ideias', 'ideas');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'ideias', 'ideas');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'gatos-e-aquecimento-global', 'cats-and-global-warming');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'gatos-e-aquecimento-global', 'gatos-y-calentamiento-global');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'gatos', 'cats');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'gatos', 'gatos');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'index', 'index');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'index', 'indice');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'index-en', 'i-m-sorry--i-can-t-assist-with-that-');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'index-en', 'indice-en');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'p-automoto', 'self-driving');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'p-automoto', 'p-automoto');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'entrevistas', 'interviews');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'entrevistas', 'entrevistas');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'minha-vida-para-mariana', 'my-life-for-mariana');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'minha-vida-para-mariana', 'mi-vida-para-mariana');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'entrevista-com-sinergia', 'interview-with-synergy');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'entrevista-com-sinergia', 'entrevista-con-sinergia');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'vender-o-carro', 'sell-the-car');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'vender-o-carro', 'vender-el-coche');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'blog-compilado-estaticamente-em-php', 'static-compiled-blog-in-php');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'blog-compilado-estaticamente-em-php', 'blog-compilado-estaticamente-en-php');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'servidor-micropub', 'micropub-server');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'servidor-micropub', 'servidor-micropub');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'interface-de-cliente-micropub', 'micropub-client-interface');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'interface-de-cliente-micropub', 'interfaz-de-cliente-micropub');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'servidor-de-webmentions', 'webmention-server');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'servidor-de-webmentions', 'servidor-de-menciones-web');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'servidor-de-indieauth-e-indietokens', 'indieauth-and-indietokens-server');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'servidor-de-indieauth-e-indietokens', 'servidor-de-indieauth-e-indietokens');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'servidor-microsub', 'microsub-server');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'servidor-microsub', 'servidor-microsub');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'hospedagem-em-ipfs', 'hosting-on-ipfs');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'hospedagem-em-ipfs', 'alojamiento-en-ipfs');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'hospedagem-em-hypercore', 'hosting-on-hypercore');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'hospedagem-em-hypercore', 'alojamiento-en-hypercore');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'copias-locais-de-links-externos', 'local-copies-of-external-links');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'copias-locais-de-links-externos', 'copias-locales-de-enlaces-externos');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'imagens-locais', 'local-images');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'imagens-locais', 'imagenes-locales');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'whostyles', 'whostyles');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'whostyles', 'estilos-de-quien');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'citacoes-com--', 'quotes-with--');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'citacoes-com--', 'citas-con--');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'anotacoes-gerais', 'general-notes');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'anotacoes-gerais', 'notas-generales');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'na-midia', 'in-the-media');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'na-midia', 'en-los-medios');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'webmentions', 'webmentions');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'webmentions', 'menciones-web');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'leilao-caixa-2024', 'caixa-auction-2024');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'leilao-caixa-2024', 'subasta-caixa-2024');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'brave-sync', 'brave-sync');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'brave-sync', 'sincronizacion-de-brave');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'dominios-legais-disponiveis', 'legal-domains-available');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'dominios-legais-disponiveis', 'dominios-legales-disponibles');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'ideias-de-tatuagens', 'tattoo-ideas');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('es', 'ideias-de-tatuagens', 'ideas-de-tatuajes');
INSERT OR REPLACE INTO settings (key, value) VALUES ('copyright', '');


INSERT INTO settings (key, value) VALUES ('intl', '{"pt-br":{"localizeddate":{"date":"d \\d\\e F \\de\\ Y","time":"H:iP","full":"l, d \\d\\e F \\d\\e Y \\à\\s H:i e","shortdate":"d\/m\/Y","shorttime":"H:i","shortfull":"d\/m\/Y H:i","daysofweek":["Domingo","Segunda-feira","Terça-feira","Quarta-feira","Quinta-feira","Sexta-feira","Sábado"],"months":["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]}},"en":{"localizeddate":{"date":"F d, Y","time":"h:i A","full":"l, F d, Y \\a\\t h:i A","shortdate":"m\/d\/Y","shorttime":"h:i A","shortfull":"m\/d\/Y h:i A","daysofweek":["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],"months":["January","February","March","April","May","June","July","August","September","October","November","December"]}},"es":{"localizeddate":{"date":"d \\d\\e F \\d\\e Y","time":"H:iP","full":"l, d \\d\\e F \\d\\e Y \\à\\s H:iP","shortdate":"d\/m\/Y","shorttime":"H:i","shortfull":"d\/m\/Y H:i","daysofweek":["Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado"],"months":["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"]}}}');
INSERT INTO settings (key, value) VALUES ('originaldaysofweek', '["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"]');
INSERT INTO settings (key, value) VALUES ('originalmonths', '["January","February","March","April","May","June","July","August","September","October","November","December"]');

INSERT INTO settings (key, value) VALUES ('kindspath', '{"article":["artigos","articles","articulos"],"bookmark":["marcadores","bookmarks"],"journal":["diarios","journals","diaries"],"Like":["Curtidas","likes","me-gusta"],"note":["notes","notas"],"photo":["fotos","photos"],"reply":["respostas","replies","respuestas"],"repost":["republicacoes","reposts","republicaciones"],"rsvp":["confirmacoes","rsvps","confirmaciones"],"jardim":["garden","jardim","jardim_digital","pensamentos","thoughts","pensamientos"]}');

CREATE TABLE IF NOT EXISTS indieauth_codes (
    code_hash TEXT PRIMARY KEY,
    client_id TEXT NOT NULL,
    redirect_uri TEXT NOT NULL,
    state TEXT,
    scope TEXT,
    code_challenge TEXT,
    code_challenge_method TEXT,
    expires_at INTEGER NOT NULL,
    me TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS indieauth_tokens (
    token_hash TEXT PRIMARY KEY,
    client_id TEXT NOT NULL,
    scope TEXT,
    me TEXT NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS activitypub_followers (
    actor_url TEXT PRIMARY KEY,
    inbox_url TEXT NOT NULL,
    shared_inbox_url TEXT
);

CREATE TABLE IF NOT EXISTS activitypub_keys (
    key_id TEXT PRIMARY KEY,
    private_key TEXT NOT NULL,
    public_key TEXT NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS activitypub_outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payload_json TEXT NOT NULL,
    target_inbox TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    created_at INTEGER NOT NULL
);

INSERT OR REPLACE INTO settings (key, value) VALUES ('activitypub_handle', 'lumen');
