# Prihlasovani a identity administrace

Admin platforma podporuje dva zpusoby prihlaseni: lokalni heslo a hostem nakonfigurovany OIDC provider. V obou pripadech ma prihlaseny uzivatel lokalni `system_user`. Je to jediny zdroj identity pro role, permission overrides, audit, editor locky a dalsi lokalni vazby.

## Rychly obrazek

```text
Local password ----\
                  -> system_user -> role -> overrides -> permissions
OIDC provider -----/

issuer + subject -> system_user_identity -> system_user
```

## Lokalni prihlaseni

Lokalni credentials najdou `system_user`, zapisi jeho ID do session a Admin z lokalnich roli a overrides vypocita opravneni. Ucet s `password_hash = NULL` se lokalne prihlasit nemuze.

## OIDC provider

OIDC provider overuje identitu. Admin prijme authorization code s PKCE, overi ID token a zkontroluje hostem definovane admission podminky. Az potom podle issuer a subject najde lokalni identitu, zapise local user ID do session a pouzije stejne lokalni RBAC jako pro heslo.

OIDC provider nerozhoduje o rolich ani permissions v Adminu. Role se z providera nesynchronizuji. Neexistuje druhy OIDC authorization system ani hardcoded role `editor`.

## Prvni a dalsi login

Pri prvnim loginu Admin po admission hleda `(issuer, subject)`. Kdyz vazba neexistuje, v jedne transakci vytvori aktivni shadow `system_user`, heslo nastavi na `NULL`, neprida roli ani overrides a vytvori `system_user_identity`. Profil muze naplnit z overenych claims. Username je stabilni technicky hash issuer a subject, nikdy se neodvozuje z e-mailu.

Shadow user bez role nema permissions. Roli a overrides mu pridava administrator primo v Adminu.

Pri dalsim loginu se vzdy pouzije stejny `system_user` podle issuer a subject. E-mail neni identity key a zmena e-mailu username nezmeni. Neprzdne profilove hodnoty lze synchronizovat z OIDC. Inactive ani soft-deleted ucet se loginem neaktivuje.

## External identity

`system_user_identity` spojuje externi login s lokalnim uctem:

- `user_id` je lokalni `system_user`
- `provider_key` popisuje technicky provider/config context
- `issuer` a `subject` jsou canonical identita

Canonical identita je pouze `issuer + subject`. E-mail se muze zmenit, proto se pro lookup ani automaticke propojovani nepouziva. Stejny subject od jineho issueru je jina identita. Kolize e-mailu pri prvnim loginu se automaticky nespojuje a login bezpecne selze.

## Profil a heslo

U external-linked uctu jsou `first_name`, `last_name` a `email` synchronizovane z OIDC providera a v Users editoru jsou jen pro cteni. Telefon, stav, role a permission overrides zustavaji lokalni. Server odmitne i rucne sestaveny request, ktery by profilova pole zmenil.

Shadow account nema lokalni heslo. Users editor ho nenabizi a server jeho nastaveni odmitne nezavisle na UI.

## Session, logout a audit

Session vzdy obsahuje local `user_id`. Zaroven si pamatuje source `local` nebo `oidc`; OIDC uchovava provider key a ID token potrebny pro provider logout. Pro zbytek aplikace je actor vzdy lokalni `system_user`.

Local i OIDC user se audituje pres local user ID: `actor_type = user` a `actor_user_id = system_user.id`.
