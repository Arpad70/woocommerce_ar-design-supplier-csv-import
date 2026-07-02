AR Design Supplier CSV Import — Uživatelská příručka

Přehled

Tento plugin importuje CSV soubory od dodavatele do produktů WooCommerce podle párování pomocí SKU. Podporuje konfigurovatelný oddělovač CSV, ohraničení polí, přítomnost hlavičky, mapování polí, přiřazení kategorií, atributy, obrázky a stav zásob.

Instalace

- Nainstalujte z GitHub release ZIP nebo přes administraci WP: Plugins > Add New > Upload Plugin a aktivujte.
- Vyžaduje aktivní WooCommerce.

Základní použití

1. Přejděte do WooCommerce > Supplier CSV Import.
2. Zadejte URL zdroje CSV (nebo nechte prázdné a nahrajte soubor ručně později).
3. Nastavte oddělovač (výchozí `;`) a ohraničení (výchozí `"`).
4. Zaškrtněte „CSV contains header row“, pokud má váš CSV hlavičku.
5. Nastavte „Match products by" na `SKU` (doporučeno) nebo `Post ID`, pokud feed obsahuje WP post id.
6. Nakonfigurujte „Field mapping" ve formátu `pole:CSV sloupec` oddělené pomocí `|`.
   - Příklad: `sku:SKU|name:Product Name|regular_price:Price|description:Desc|categories:Category`
7. Vyberte chování importu: Create/Update, Create only nebo Update only.
8. Zapněte nebo vypněte import atributů/obrázků/stavu zásob podle potřeby.

Pravidla mapování polí

- Levá strana (pole) je interní pole produktu (např. `sku`, `name`, `regular_price`, `description`, `short_description`, `weight`, `length`, `width`, `height`, `categories`, `images`, `attributes`, `stock_quantity`).
- Pravá strana je přesný název hlavičky v CSV (když je hlavička přítomna) nebo štítek sloupce použitý skriptem.
- `categories` a `images` přijímají více hodnot oddělených `|` nebo `,`.
- `attributes` očekává páry `Name:Value`; podporovány jsou jednoduché atributy (taxonomy), které budou vytvořeny.

Obrázky

- Pokud je import obrázků povolen, obrázky jsou staženy a přidány do galerie produktu. Plugin používá WordPress media sideload. Zkontrolujte, že PHP má síťový přístup a že jsou dostupné potřebné funkce (cURL nebo allow_url_fopen).

Překlady

- Plugin obsahuje překlady (.po/.mo) pro `en_US`, `sk_SK` a `cs_CZ` ve složce `languages/`. WordPress vybere překlad podle jazyka webu.

CLI testování

- V pluginu je jednoduchý testovací skript `scripts/test-import-config.php` pro ověření parseru a mapování v CLI. Spusťte ho z adresáře pluginu:

```sh
php scripts/test-import-config.php
```

Řešení problémů

- Pokud se stránka s nastavením zhroutí kvůli chybě callbacku, ověřte, že soubory pluginu jsou aktuální (metody renderu polí musí být public pro Settings API).
- Pokud nelze zapsat stažené CSV, zkontrolujte práva adresáře `wp-content/import` (plugin vytvoří `import` v `ABSPATH`).

Podpora

Vytvořte issue na GitHubu s detaily a ukázkovým CSV (anonymizovaným) a já se podívám.
