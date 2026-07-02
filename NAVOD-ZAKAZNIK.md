# Návod pro zákazníka

Tento dokument obsahuje stručný postup pro instalaci a použití pluginu AR Design Supplier CSV Import ve WordPressu.

## 1. Instalace pluginu

1. Stáhněte si nejnovější ZIP archiv z GitHub release.
2. V administraci WordPress otevřete sekci Pluginy -> Přidat nový.
3. Klikněte na Nahrát plugin.
4. Vyberte stažený ZIP soubor a potvrďte instalaci.
5. Po úspěšné instalaci klikněte na Aktivovat.

## 2. Nastavení importu

1. V administraci otevřete nabídku WooCommerce.
2. Vyberte položku Supplier CSV Import.
3. Vložte odkaz na CSV soubor dodavatele.
4. Uložte nastavení.

## 3. Spuštění importu

- Import spusťte ručně z administrace.
- Pokud je dostupný plánovač úloh, lze import spouštět i automaticky.

## 4. Jak plugin pracuje

Plugin páruje produkty podle SKU. Pokud produkt se stejným SKU existuje, aktualizuje ho. Pokud neexistuje, vytvoří nový produkt.

## 5. Aktualizace

Při nové verzi pluginu:

1. stáhněte novou verzi ZIP souboru,
2. nahradit starou verzi přes WordPress,
3. aktivovat novou verzi.

## 6. Tipy

- Ujistěte se, že CSV soubor je dostupný přes URL.
- Dbejte na správné SKU, protože právě podle něj probíhá párování.
- Po změně zdrojového souboru zkontrolujte, zda import běží správně.

Pokud budete potřebovat pomoc, lze plugin rozšířit o další pole, automatické spouštění nebo detailnější logy importu.
