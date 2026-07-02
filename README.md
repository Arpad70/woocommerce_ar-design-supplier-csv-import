# AR Design Supplier CSV Import

Samostatný plugin pro automatický import dodavatelského CSV do WooCommerce.

## Co plugin dělá

- stahuje CSV z externí URL,
- ukládá stažený soubor do adresáře /import,
- importuje a aktualizuje produkty podle SKU,
- aktualizuje cenu, popis, rozměry, atributy, kategorie a obrázky.

## Předpoklady

- WordPress 6.2 nebo novější
- WooCommerce aktivní
- PHP 7.4 nebo novější
- přístup k externí URL s CSV souborem

## Instalace přes WordPress

1. Stáhněte ZIP archiv z GitHub release.
2. V administraci WordPress otevřete Pluginy -> Přidat nový.
3. Klikněte na Nahrát plugin.
4. Vyberte stažený ZIP soubor a potvrďte instalaci.
5. Po dokončení klikněte na Aktivovat.
6. V nabídce WooCommerce otevřete Supplier CSV Import a nastavte URL CSV.

## Instalace z GitHub release

- repozitář: https://github.com/Arpad70/woocommerce_ar-design-supplier-csv-import
- release asset: https://github.com/Arpad70/woocommerce_ar-design-supplier-csv-import/releases/download/v1.0.0/ar-design-supplier-csv-import-v1.0.0.zip

## Konfigurace

Po aktivaci pluginu:

1. otevřete WooCommerce -> Supplier CSV Import,
2. vložte URL k CSV souboru,
3. uložte nastavení,
4. spusťte import.

Plugin pracuje s SKU jako hlavním klíčem pro párování produktů. Pokud produkt se stejným SKU existuje, plugin ho aktualizuje. Pokud neexistuje, vytvoří nový.

## Spuštění importu

Import lze spustit:

- ručně z administrace,
- nebo pomocí plánovaného procesu, pokud je v prostředí dostupný cron/WordPress scheduler.

## Aktualizace

Aktualizace je možná dvěma způsoby:

1. ruční nahrání nového ZIP souboru přes WordPress,
2. instalace nové verze z GitHub release.

Po aktualizaci zkontrolujte, zda je v nastavení stále správná URL CSV a zda import funguje podle očekávání.

## Odinstalace

1. Deaktivujte plugin v sekci Pluginy.
2. Odinstalujte ho z WordPress.
3. Volitelně smažte stažené CSV soubory z adresáře /import.

## Tipy

- CSV by mělo obsahovat stabilní SKU.
- Doporučuje se pravidelně kontrolovat, zda dodavatelský soubor vrací očekávané sloupce.
- Při problémech zkontrolujte logy a oprávnění adresáře /import.
