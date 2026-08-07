# Changelog

## v1.3 — Marketplace OAuth z panelu

- Dodano tabelę `marketplace_app_credentials`.
- Dodano model `MarketplaceAppCredential`.
- Dodano panel `Ustawienia marketplace`.
- Dodano formularz dodawania/edycji danych aplikacji Allegro/eBay.
- Allegro OAuth czyta `client_id`, `client_secret`, `redirect_uri` z bazy, nie z `.env`.
- eBay OAuth czyta `client_id`, `client_secret`, `redirect_uri`, `scopes` z bazy, nie z `.env`.
- Połączony kanał zapisuje w `settings.marketplace_app_credential_id`, której aplikacji użyto.
- Refresh tokenów działa na podstawie aplikacji przypisanej do kanału.

## v1.2 — Marketplace OAuth

- Pierwszy szkielet OAuth Allegro/eBay.

## v1.1 — Panel webowy

- Dodawanie WooCommerce, lista zamówień, szczegóły, InPost actions.

## v1.0 — Core starter

- Modele, migracje, connector WooCommerce, joby i scheduler.
