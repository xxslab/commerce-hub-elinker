# Commerce Hub v1.1 Starter

Panel integrujący zamówienia z wielu kanałów sprzedaży: WooCommerce, Allegro, eBay oraz obsługę przesyłek przez InPost.

Ta wersja jest już praktyczniejsza niż v1.0: ma prosty panel webowy Blade, ekrany integracji WooCommerce, listę zamówień, szczegóły zamówienia, akcje InPost i instrukcję instalacji.

## Najważniejsze

- Startujemy realnie od WooCommerce.
- Allegro/eBay są przygotowane architektonicznie, ale OAuth i ekrany callbacków są następnym etapem.
- InPost jest przygotowany jako pierwszy kurier: create shipment, label, tracking.
- To jest overlay do świeżego Laravel 11/12, nie pełna aplikacja z vendorem.

## Instalacja

Zobacz `INSTALL.md`.

## Zakres v1.1

```text
WooCommerce:
- dodanie sklepu
- test połączenia
- synchronizacja zamówień
- lista zamówień
- szczegóły zamówienia
- pozycje produktów
- lokalne statusy
- push tracking jako order note

InPost:
- utworzenie przesyłki
- pobranie etykiety PDF
- odświeżenie trackingu

Core:
- companies
- sales_channels
- commerce_orders
- order_items
- shipments
- sync_logs
- queue jobs
- scheduler
- web UI
```

## Kolejny etap developmentu

```text
1. Dokończyć OAuth Allegro: redirect + callback + token refresh.
2. Dokończyć OAuth eBay: redirect + callback + token refresh.
3. Dodać ekrany dodawania Allegro/eBay.
4. Dodać realne mapowanie statusów Allegro/eBay.
5. Dodać update statusu do źródła, nie tylko lokalnie.
6. Dodać drugi carrier: DPD albo DHL.
7. Dodać faktury/Fakturownia.
```


## v1.2 Marketplace OAuth

Ta wersja dodaje realną strukturę łączenia Allegro i eBay przez OAuth:

- `Połącz Allegro` w panelu integracji,
- `Połącz eBay` w panelu integracji,
- callbacki `/integrations/allegro/callback` i `/integrations/ebay/callback`,
- zapis tokenów marketplace w `sales_channels.credentials_encrypted`,
- ręczny refresh tokenów z panelu,
- job i scheduler do cyklicznego odświeżania tokenów.

Szczegóły: `docs/MARKETPLACE_OAUTH.md`.
