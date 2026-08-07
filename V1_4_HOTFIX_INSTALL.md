# Commerce Hub v1.4 hotfix — Plesk

## Co poprawia

- stabilizuje WooCommerceClient: pełny URL sklepu, `orderby=date`, poprawny typ parametrów,
- stabilizuje WooCommerceOrderSyncService: pagination, brak duplikatu getOrders, poprawne `$client->getOrders([...])`,
- dodaje status kanału: `idle`, `syncing`, `error`,
- dodaje `last_sync_at`, `last_sync_count`, `last_error`,
- dodaje `/queue` z podglądem jobs/failed_jobs oraz stanem kanałów,
- dodaje automatyczny scheduler dla sync co 5 minut i queue worker co minutę,
- przywraca bezpieczny `routes/console.php` dla starszego Laravela/Pleska.

## Instalacja overlay

1. Wgraj ZIP do katalogu głównego Laravela, obok `artisan`.
2. Rozpakuj do tymczasowego katalogu.
3. Skopiuj foldery `app`, `database`, `resources`, `routes`, `docs`, `scripts` do katalogu głównego aplikacji.
4. Odpal w Plesk Artisan:

```
optimize:clear
migrate
```

5. Ustaw w `.env`:

```
QUEUE_CONNECTION=database
```

6. Dodaj zaplanowane zadanie w Plesku co minutę:

```
cd /dysk3/vhosts/dev.dosieci.pl/elink.dosieci.pl && /opt/plesk/php/8.3/bin/php artisan schedule:run
```

Jeśli używasz PHP 8.1, zmień ścieżkę na `/opt/plesk/php/8.1/bin/php`.

## Sprawdzenie

- Dashboard: `/`
- Zamówienia: `/orders`
- Kolejka: `/queue`

Na dashboardzie każda integracja ma lampkę:

- zielona: `idle`,
- żółta: `syncing`,
- czerwona: `error`.

## Ręczne odpalenie kolejki

W Plesk Artisan:

```
commerce-hub:sync-orders --force
queue:work --stop-when-empty
```
