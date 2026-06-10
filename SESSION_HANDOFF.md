# Session Handoff — Optic-Lenses (Alwaleed Optics Products)

**Date :** 2026-06-10 (dernière mise à jour)  
**Plugin :** `wp-content/plugins/Optic-Lenses`  
**Version déclarée :** 1.2.3 (`woocommerce-optic-product.php`) — *backorder + menu admin ajoutés en session 2026-06-10, bump version à planifier*  
**Thème cible boutique :** Flatsome (parent ou enfant)

Ce document résume tout le travail réalisé sur le plugin (sessions Cursor cumulées), pour permettre à un autre développeur (ou une future session IA) de reprendre sans perte de contexte.

> **Convention :** à chaque prompt / livraison significative, mettre à jour ce fichier (`SESSION_HANDOFF.md`). Lors d’un bump de version, synchroniser aussi `CHANGELOG.md` et `woocommerce-optic-product.php` / `composer.json`.

---

## 1. Résumé exécutif

### Session 2026-06-10 (courante)

1. **Backorder** — paramétrage global (Settings) + override par produit interne ; stock vendable = stock physique + allowance backorder − consommé − panier.
2. **Menu admin WordPress** — menu principal **Alwaleed Optics** (sous Dashboard) ; migration **Settings** et **Import** hors du menu WooCommerce.

### Session 2026-06-09 (précédente)

1. **Lentilles couleur** — choix **No power / Power** (défaut : No power).
2. **Prix unique** — fin des fourchettes min–max ; prix basé sur la **sélection par défaut** (option B).
3. **Panier / checkout** — total par section œil + habillage **Flatsome moderne**.
4. **Règles métier affinées** — « sans puissance » = SPH **+0.00** ; autres divisions = **prix le plus bas**.
5. **UI fiche produit** — toggle No power/Power style **Eyewa** (pill) ; division et bloc prix formulaire **masqués** (v1.2.0).
6. **Version** — bump **1.2.3** (qty centrées, labels grille).

Aucun commit git n’a été demandé ni créé pendant ces sessions.

---

## 2. Fonctionnalités livrées

### 2.1 Lentilles couleur — No power / Power

**Division concernée :** `color_lenses` uniquement (`WC_Optic_SKU::division_supports_no_power_mode()`).

| Mode | Comportement client |
|------|---------------------|
| **No power** (défaut) | Radio sélectionné à l’ouverture. Masque prescription SPH et « 2 puissances différentes ». Quantité seule. Résout automatiquement le produit interne **+0.00**. |
| **Power** | Affiche sélecteur SPH (+ quantité, option bi-œil si plusieurs enfants avec puissances). Exclut +0.00 de la cascade JS. |

**Définition « No power » côté données :**  
Ce n’est **pas** un SPH vide. Un produit interne est « no power » si son terme catalogue SPH est reconnu comme **plano / +0.00** :

- `WC_Optic_Catalog::sph_term_is_zero_power( $row )`
- `WC_Optic_Catalog::sph_value_is_zero_power( $value )` — reconnaît `+0.00`, `0.00`, `0`, `plano`, etc.
- Filtre WP : `wc_optic_sph_is_zero_power`

**Admin :** chaque variante no-power doit avoir un SPH **+0.00** explicite dans le catalogue (prix + stock obligatoires comme les autres enfants).

**UI toggle (style [Eyewa](https://eyewa.com/ae-en/diva-color-contact-lenses-pack-of-2.html)) :**

- Conteneur `.wc-optic-power-mode` : fond gris `#f4f4f5`, hauteur 53px, `border-radius: 60px`, padding 4px.
- Onglets `.wc-optic-power-mode__tab` : `flex: 1`, transition 350ms.
- Actif (`input:checked + label`) : fond blanc, texte `#111827`, `font-weight: 400`.
- Inactif : texte `#6b7280`, `font-weight: 600`.
- `data-testid="tab_no_power"` / `tab_power` ; IDs `#wc_optic_tab_no_power`, `#wc_optic_tab_power`.
- Pas de libellé visible « Power type » (legend / `aria-label` uniquement).

**Fichiers clés :**

- `templates/single-product/add-to-cart/optic_product.php` — structure pill `input` + `label`
- `assets/css/frontend.css` — styles `.wc-optic-power-mode*`
- `assets/js/frontend.js` — `togglePowerMode()`, `isNoPowerMode()`, `syncNoPowerChildFields()`
- `includes/class-wc-optic-cart.php` — parse `wc_optic_power_mode`, payload `power_mode`
- `includes/class-wc-optic-sku.php` — `child_is_no_power()`, `find_no_power_child()`, matrice `noPowerChild` / `children` séparés

---

### 2.5 Fiche produit — éléments masqués (v1.2.0)

| Élément | Statut |
|---------|--------|
| `.wc-optic-division` | **Supprimé** du template (plus d’affichage « Optical division »). |
| `.wc-optic-pricing` visible | **Supprimé** — bloc conservé en `hidden` pour sync JS vers le prix WooCommerce/Flatsome (`.summary > .price`). |

Le client voit le prix uniquement via le bloc prix standard du thème ; le formulaire optique ne duplique plus prix ni division.

---

### 2.6 Fiche produit — ajustements CSS (v1.2.3)

| Élément | Changement |
|---------|------------|
| `#wc_optic_qty`, `#wc_optic_qty_left`, `#wc_optic_qty_right` | `text-align: center` |
| `.wc-optic-config-table__label` | `padding-top` retiré (règle commentée dans `frontend.css`) |

Fichier : `assets/css/frontend.css`.

---

### 2.2 Affichage prix — option B (prix unique)

**Avant :** fourchette `min – max` via `format_price_range_html()` / `wc_format_price_range()`.

**Après :** un seul prix via `WC_Optic_Pricing::format_display_price_html()`.

| Contexte | Règle |
|----------|--------|
| **Lentilles couleur** | Prix du produit interne **No power** (+0.00) |
| **Autres divisions** | Prix du produit interne **le plus bas** (parmi enfants actifs et complets) |
| **Fiche produit — sélection validée** | Prix exact de la configuration (JS) |
| **Mode Power sans SPH choisi** | Prix unitaire vide jusqu’à sélection complète |
| **Listes / grilles / `get_price_html`** | Prix par défaut (même règle) |

**Méthodes centrales :**

```text
WC_Optic_SKU::get_default_display_child( $product )
WC_Optic_SKU::get_default_display_price( $product )
WC_Optic_Pricing::format_display_price_html( $product )
WC_Optic_Pricing::get_unit_price( $product )  → utilise le prix par défaut
```

**Sauvegarde admin :** `WC_Optic_SKU::persist_child_data()` synchronise le prix parent WooCommerce sur `get_default_display_price()` (plus le minimum global).

**JS fiche produit :** `wcOpticFront.defaultPrice` / `defaultPriceHtml` (remplace `priceRange` / `priceRangeHtml`).

**Alias déprécié :** `format_price_range_html()` délègue à `format_display_price_html()`.

> **Note post-session :** resauvegarder les fiches produits existantes si le prix parent WooCommerce doit être resynchronisé.

---

### 2.3 Panier / checkout — total par section œil

Dans le résumé HTML client (`WC_Optic_Cart::render_line_eye_column()`), chaque colonne OS / OD (ou bloc « même puissance ») affiche en bas :

- **Total** = prix unitaire × quantité de la section
- Classe CSS : `wc-optic-line-summary__meta-row--total`

`get_eye_admin_summary()` retourne désormais `line_total`.

---

### 2.4 Flatsome — panier & paiement modernes

**Nouveau module :** `includes/class-wc-optic-flatsome.php`  
Enregistré dans `class-wc-optic-plugin.php` et `class-wc-optic-autoload.php`.

**Activation :** détection thème dont le slug contient `flatsome` (parent ou enfant).

**Body classes :**

- `wc-optic-flatsome-cart` — page panier
- `wc-optic-flatsome-checkout` — page paiement

**Assets :** `assets/css/flatsome-cart-checkout.css` (+ `frontend.css`, `cart.js` sur panier).

**Effets visuels :**

- Lignes panier en cartes (coins arrondis, ombre)
- Totaux panier en carte sticky (desktop)
- Formulaire checkout en sections cartes
- Moyens de paiement en cartes
- Boutons arrondis pleine largeur
- Quantités optiques restructurées (`wc-optic-cart-qty__field`)

**Délégation assets :** `WC_Optic_Cart::enqueue_cart_scripts()` ne charge rien si Flatsome actif (évite doublon ; Flatsome module gère tout).

---

### 2.7 Backorder — achat au-delà du stock (session 2026-06-10)

**Objectif :** autoriser la vente au-delà du stock physique, avec une allowance configurable globalement et optionnellement par produit interne.

#### Settings globaux (`Alwaleed Optics → Settings`)

| Option | Clé WP | Comportement |
|--------|--------|--------------|
| **Allow backorder** | `wc_optic_backorder_enabled` (`yes`/`no`) | Active le backorder sur toute la boutique |
| **Backorder quantity** | `wc_optic_backorder_qty` | Unités **supplémentaires** vendables par produit interne (ex. stock 5 + backorder 5 → **max 10**) |

UI : case à cocher + champ numérique (masqué si backorder désactivé). JS : `assets/js/admin-settings.js`.

#### Fiche produit — produit interne

Sous **Stock quantity** :

| Élément | Rôle |
|---------|------|
| **Backorder allowed** | Champ `disabled` — valeur effective affichée |
| **Custom** | Case à cocher : override du backorder global pour cet interne |
| Champ qty custom | Éditable si Custom coché ; sinon règle globale |
| Note consommé | Affiche `N backorder unit(s) already sold` si `backorder_consumed > 0` |

Méta enfant : `backorder_custom`, `backorder_qty`, `backorder_consumed` (dans `_optic_child_configs`).

#### Formule stock disponible

```text
sellable = stock_physique + backorder_allowance − backorder_consumed
remaining = sellable − quantité_réservée_panier
```

Exemple : stock 5, backorder 5, panier 3 → **7** disponibles.

#### Méthodes PHP centrales

```text
WC_Optic_SKU::is_backorder_enabled()
WC_Optic_SKU::get_global_backorder_qty()
WC_Optic_SKU::get_child_backorder_qty( $config )
WC_Optic_SKU::get_child_sellable_qty( $config )
WC_Optic_SKU::apply_child_stock_delta( &$config, $delta )  // commande : stock d’abord, puis backorder
WC_Optic_SKU::preserve_child_backorder_consumed( $product, $children )  // sauvegarde admin
WC_Optic_Cart::get_remaining_child_stock( $product, $config )  // utilise sellable qty
```

**Boutique :** matrice JS (`stock`, `inStock`) et validation panier/checkout utilisent `get_remaining_child_stock()` — pas de changement JS frontend dédié requis.

**Fichiers :** `class-wc-optic-sku.php`, `class-wc-optic-cart.php`, `class-wc-optic-admin-settings.php`, `class-wc-optic-admin-product.php`, `admin-settings.js`, `admin-product.js`, `admin.css`.

---

### 2.8 Menu admin — Alwaleed Optics (session 2026-06-10)

**Avant :** Settings et Import étaient des sous-menus **WooCommerce**.

**Après :** menu principal WordPress :

```text
Dashboard
Alwaleed Optics          ← position 3, dashicons-visibility
  ├── Settings           page=wc-optic-settings
  └── Import             page=wc-optic-import
```

**Classe :** `includes/admin/class-wc-optic-admin-menu.php` (`WC_Optic_Admin_Menu`).

| Constante | Valeur | Usage |
|-----------|--------|--------|
| `PARENT_SLUG` | `wc-optic-settings` | Slug menu parent |
| `MENU_POSITION` | `3` | Juste sous Dashboard |
| `SETTINGS_SCREEN` | `toplevel_page_wc-optic-settings` | Hook `admin_enqueue_scripts` Settings |
| `IMPORT_SCREEN` | `wc-optic-settings_page_wc-optic-import` | Hook `admin_enqueue_scripts` Import |

Les URLs admin (`admin.php?page=wc-optic-settings` / `wc-optic-import`) sont **inchangées** — liens internes et favoris restent valides.

Enregistrement : `WC_Optic_Admin_Menu::hooks()` dans `class-wc-optic-plugin.php` ; map autoload dans `class-wc-optic-autoload.php`.

---

## 3. Architecture & fichiers modifiés

### PHP — includes

| Fichier | Rôle |
|---------|------|
| `admin/class-wc-optic-admin-menu.php` | **Nouveau (2026-06-10)** — menu principal Alwaleed Optics |
| `admin/class-wc-optic-admin-settings.php` | Settings globaux + backorder ; plus de sous-menu WooCommerce |
| `admin/class-wc-optic-admin-import.php` | Import catalogue ; hook screen sous menu Alwaleed Optics |
| `admin/class-wc-optic-admin-product.php` | Champs backorder par produit interne |
| `class-wc-optic-catalog.php` | `sph_term_is_zero_power()`, `sph_value_is_zero_power()` |
| `class-wc-optic-sku.php` | No-power, prix défaut, **backorder**, matrice storefront, `persist_child_data` |
| `class-wc-optic-pricing.php` | `format_display_price_html()`, filtre `get_price_html` |
| `class-wc-optic-cart.php` | Panier, **stock sellable/backorder**, `apply_child_stock_delta` |
| `class-wc-optic-frontend.php` | Stock HTML, `product_is_in_stock()` via remaining |
| `class-wc-optic-flatsome.php` | Détection Flatsome + assets panier/checkout |
| `class-wc-optic-plugin.php` | `WC_Optic_Admin_Menu::hooks()`, Flatsome, etc. |
| `class-wc-optic-autoload.php` | Map classes admin + Flatsome |

### Templates

| Fichier | Changements |
|---------|---------------|
| `templates/single-product/add-to-cart/optic_product.php` | Pill No power/Power ; pas de `.wc-optic-division` ; `.wc-optic-pricing` **hidden** (sync JS) |

### Assets

| Fichier | Changements |
|---------|---------------|
| `assets/js/frontend.js` | Power mode, prix défaut, pas de range |
| `assets/js/cart.js` | Inchangé (sync qty) |
| `assets/js/admin-settings.js` | Toggle visibilité champ backorder qty global |
| `assets/js/admin-product.js` | Toggle Custom backorder, sync affichage par interne |
| `assets/css/frontend.css` | Pill Eyewa power mode, line-summary total, qty centrées, labels grille |
| `assets/css/admin.css` | Styles backorder admin, `.wc-optic-is-hidden` |
| `assets/css/flatsome-cart-checkout.css` | Styles panier/checkout Flatsome |

---

## 4. Flux données — ajout au panier (lentilles couleur)

```text
POST wc_optic_power_mode = no_power | power
POST wc_optic_qty / wc_optic_qty_left / wc_optic_qty_right
POST wc_optic_{left|right}_sph (si mode Power)
POST wc_optic_different_power (si applicable)

→ WC_Optic_Cart::parse_request()
  → power_mode dans payload
  → no_power : find_no_power_child() + build_eye_payload_from_child()
  → power   : parse_eye_child() classique

→ Payload stocké sous clé _wc_optic (CART_KEY)
```

**Payload champs notables :**

```php
[
  'power_mode' => 'no_power' | 'power',
  'same_power' => bool,
  'qty_mode'   => 'single' | 'dual',
  'left'       => [ child_id, unit_price, powers[], ... ],
  'right'      => ...,
  'line_qty'   => int,
  'line_total' => float,
]
```

---

## 5. Matrice storefront JS (`wcOpticFront.matrix`)

Produite par `WC_Optic_SKU::get_storefront_matrix()` :

```javascript
{
  division: 'color_lenses',
  supportsNoPowerMode: true,
  noPowerChild: { id, price, stock, inStock },  // +0.00
  powers: ['sph'],
  children: [ ... ],  // enfants AVEC puissance uniquement
  terms: { sph: { id: label } },
  labels: { sph: 'SPH' }
}
```

Le JS résout l’enfant via cascade SPH (`childrenMatching`, `resolveChildForEye`) ou directement `noPowerChild` en mode No power.

---

## 6. Configuration admin requise

### Produit type Optic — division Color lenses

1. Créer au moins un **produit interne No power** : SPH = **+0.00** (terme catalogue), autres champs catalogue remplis, prix + stock.
2. Créer les produits internes **avec puissance** (autres valeurs SPH) pour le mode Power.
3. Vérifier qu’aucune combinaison de puissances n’est dupliquée (`validate_unique_power_combinations`).

### Catalogue SPH

S’assurer qu’une entrée **+0.00** existe et est reconnaissable (`name`, `slug` ou `sku_fragment`). Sinon utiliser le filtre `wc_optic_sph_is_zero_power`.

### Autres divisions (toric, multifocal, etc.)

- Pas de toggle No power/Power.
- Prix affiché en boutique = **minimum** des produits internes actifs.
- Client doit sélectionner toutes les puissances de la division avant add-to-cart.

### Backorder

1. **Alwaleed Optics → Settings** : cocher **Allow backorder**, définir **Backorder quantity** (ex. 5).
2. Par défaut, tous les produits internes héritent de cette allowance.
3. Sur la fiche produit optique : cocher **Custom** sur un interne pour un backorder spécifique.
4. Vérifier en boutique : quantité max = stock + backorder (moins panier).

### Navigation admin

- **Alwaleed Optics → Settings** — catalogue, divisions, paramètres globaux (selector UI, backorder).
- **Alwaleed Optics → Import** — import Excel/CSV par onglet catalogue.

---

## 7. i18n ajoutés (`wc-optic`)

| Clé / texte | Usage |
|-------------|--------|
| No power | Radio + résumé panier |
| Power | Radio |
| Power type | `aria-label` / legend (non visible à l’écran) |
| This product is not available without power. | Erreur no-power indisponible |
| Total | Total par section œil (panier) |
| Qty | Quantité panier (mode single) |
| Allow backorder | Settings globaux |
| Backorder quantity | Settings globaux |
| Backorder allowed | Produit interne (admin) |
| Custom | Override backorder par interne |
| N backorder unit(s) already sold | Note admin consommation backorder |
| Settings / Import | Sous-menus Alwaleed Optics |

Domaine : `wc-optic` — traduction WPML via String Translation si actif.

---

## 8. Tests manuels recommandés

### Lentilles couleur

- [ ] Ouverture fiche : **No power** sélectionné, pas de SPH, quantité seule, prix = +0.00
- [ ] Passage en **Power** : SPH visible, +0.00 absent des options
- [ ] Add-to-cart No power → panier : `Power type: No power`, pas de puissances listées
- [ ] Add-to-cart Power → panier : SPH affiché, total section correct
- [ ] Stock rupture no-power → bouton désactivé / message
- [ ] Toggle pill : onglet actif fond blanc, transition fluide, pleine largeur

### Fiche produit — UI

- [ ] Pas de ligne « Optical division »
- [ ] Pas de bloc prix dans le formulaire (prix thème uniquement)
- [ ] Prix thème se met à jour au changement No power / Power / SPH

### Prix

- [ ] Grille boutique : **un seul prix** (pas de `X – Y`)
- [ ] Color lenses : prix = no-power
- [ ] Toric / multifocal : prix = **plus bas** des internes
- [ ] Fiche : changement de sélection met à jour prix + total estimé
- [ ] Mode Power sans SPH : prix unitaire vide, total masqué

### Panier / checkout

- [ ] Total par colonne OS/OD (unitaire × qty)
- [ ] Flatsome : cartes panier, totaux sticky, checkout modernisé
- [ ] Quantités OS/OD optiques synchronisent `line_qty` WooCommerce

### Admin

- [ ] Sauvegarde produit : prix parent = prix par défaut (no-power ou min)
- [ ] Produit interne +0.00 sans doublon catalogue
- [ ] Menu **Alwaleed Optics** visible sous Dashboard (pas sous WooCommerce)
- [ ] Settings et Import accessibles depuis le nouveau menu

### Backorder

- [ ] Settings : activer backorder + qty 5 → sauvegarde OK
- [ ] Interne stock 5 + backorder global 5 → fiche client max qty **10**
- [ ] 3 en panier → max qty **7** sur la fiche
- [ ] Custom sur un interne (ex. backorder 2) → seul cet interne utilise 2
- [ ] Commande qui dépasse le stock physique → `backorder_consumed` incrémenté, stock physique à 0
- [ ] Annulation commande → restauration stock + backorder_consumed

---

## 9. Points d’attention / limites connues

1. **`find_no_power_child()`** retourne le **premier** enfant +0.00 trouvé — si plusieurs variantes no-power (packs différents), seul le premier est utilisé en mode No power.
2. **Flatsome** : styles basés sur la structure WooCommerce standard ; un override template Flatsome très custom peut nécessiter des ajustements CSS.
3. **CHANGELOG.md** mis à jour à chaque bump — dernière entrée **[1.2.3] — 2026-06-09** (voir aussi [1.2.2], [1.2.1], [1.2.0]).
4. **Version plugin** : **1.2.3** (`woocommerce-optic-product.php`, `composer.json`). Convention : toujours synchroniser `CHANGELOG.md` + `SESSION_HANDOFF.md` lors d’un changement de version.
5. **`format_price_range_html()`** conservé en alias déprécié ; aucun appel interne ne produit plus de fourchette.
6. Thème Flatsome **non présent** dans le workspace local au moment du dev — tests visuels à faire sur l’environnement WAMP réel.
7. Couleurs du toggle Eyewa sont des **approximations** (#f4f4f5, #111827) — ajuster si charte Alwaleed différente.
8. **Backorder + menu admin (2026-06-10)** : pas encore de bump version ni entrée `CHANGELOG.md` — à faire avant release (suggéré **1.2.4**).
9. **Backorder désactivé globalement** : champs Custom masqués en admin produit ; `get_child_backorder_qty()` retourne 0.
10. **`backorder_consumed`** est conservé à la sauvegarde produit via `preserve_child_backorder_consumed()` — ne pas supprimer le hidden field admin.

---

## 10. Pistes non traitées (hors scope session)

- Masquer l’option **Power** si aucun enfant avec puissance n’existe.
- Afficher « À partir de » au lieu du prix sec (débattu, non retenu).
- Commit git.
- Tests automatisés PHPUnit / E2E.
- Traductions WPML des nouvelles chaînes.
- Admin : indication visuelle « variante No power » sur les produits internes.
- Bump version **1.2.4** + `CHANGELOG.md` pour backorder et menu admin.
- Commit git.

---

## 11. Reprise rapide — commandes utiles

```bash
# Depuis la racine du plugin
cd wp-content/plugins/Optic-Lenses

# Vérifier syntaxe PHP
php -l includes/class-wc-optic-sku.php
php -l includes/class-wc-optic-cart.php
php -l includes/class-wc-optic-pricing.php
php -l includes/class-wc-optic-flatsome.php
php -l includes/admin/class-wc-optic-admin-menu.php
php -l includes/admin/class-wc-optic-admin-settings.php
```

**Fichiers à lire en premier pour reprendre :**

1. `includes/class-wc-optic-sku.php` — enfants, no-power, prix défaut, **backorder**
2. `includes/class-wc-optic-cart.php` — panier, **stock sellable**, commande
3. `includes/admin/class-wc-optic-admin-menu.php` — menu admin Alwaleed Optics
4. `includes/admin/class-wc-optic-admin-settings.php` — settings globaux + backorder
5. `assets/js/frontend.js` — UX fiche produit boutique
6. `includes/class-wc-optic-flatsome.php` + `assets/css/flatsome-cart-checkout.css` — habillage Flatsome

---

## 12. Historique décisions produit (session)

| Sujet | Décision |
|-------|----------|
| No power = SPH vide ? | **Non** — SPH catalogue **+0.00** |
| Prix boutique | **Option B** — prix sélection par défaut, pas de range |
| Autres divisions — prix défaut | **Prix le plus bas** (pas le premier enfant) |
| Total panier par œil | **Oui** — fin de chaque section `wc-optic-line-summary__eye` |
| Panier Flatsome | Module CSS dédié, cartes + sticky |
| Division sur fiche | **Masquée** (info admin uniquement) |
| Prix dans formulaire | **Masqué** — prix via thème + sync JS cachée |
| Toggle No power/Power | Style **pill Eyewa** (réf. eyewa.com) |
| Backorder | Stock vendable = physique + allowance − consommé − panier |
| Backorder par interne | **Custom** checkbox ; sinon règle globale Settings |
| Menu admin | **Alwaleed Optics** top-level (pos. 3), plus sous WooCommerce |
| Mise à jour handoff | **À chaque prompt** significatif — mettre à jour `SESSION_HANDOFF.md` |
| Règle Cursor | `.cursor/rules/session-handoff.mdc` (`alwaysApply: true`) — impose la mise à jour du handoff |

---

*Dernière mise à jour : 2026-06-10 — backorder, menu Alwaleed Optics, règle Cursor session-handoff.*
