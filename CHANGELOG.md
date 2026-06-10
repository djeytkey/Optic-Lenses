# Changelog

Toutes les modifications notables de **Alwaleed Optics Products** sont documentées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).

## [1.2.0] — 2026-06-09

### Ajouté

- **Lentilles couleur** : choix **No power** / **Power** (défaut No power) ; no power = SPH catalogue **+0.00**.
- **Prix unique** en boutique (plus de fourchette min–max) : no power pour color lenses, prix le plus bas pour les autres divisions.
- **Total par section** œil dans le résumé panier/checkout (`wc-optic-line-summary`).
- Module **Flatsome** : panier et paiement en cartes (`class-wc-optic-flatsome.php`, `flatsome-cart-checkout.css`).
- Détection SPH plano : `WC_Optic_Catalog::sph_term_is_zero_power()`.
- Document **`SESSION_HANDOFF.md`**.

### Modifié

- Fiche produit : division optique et bloc prix du formulaire **masqués** (prix WooCommerce / Flatsome inchangé ; sync JS via éléments cachés).
- `format_display_price_html()` remplace l’affichage par fourchette ; prix parent synchronisé sur le produit interne par défaut.

## [1.1.0] — 2026-06-03

### Ajouté

- **QR codes** pour les SKU internes (préparation des commandes), visibles **uniquement en admin** :
  - fiche produit : aperçu sous chaque produit interne, mis à jour en AJAX avec l’aperçu SKU ;
  - commandes : bloc de préparation par ligne optique.
- Dépendance Composer **`chillerlan/php-qrcode`** (PNG si GD, sinon SVG).
- Classe **`WC_Optic_QR`** pour la génération des codes.
- **Résumé panier / checkout** (client) sur le modèle admin, **sans SKU ni QR** :
  - colonnes OS / OD (ou un bloc « même puissance ») ;
  - puissances sélectionnées, prix unitaire, quantité.
- Feuilles de style dédiées : **`assets/css/admin-order.css`**, styles panier dans **`frontend.css`**.
- Template WooCommerce **`templates/cart/cart-item-data.php`** (pas de libellé `optic-line`, pas de `wpautop` sur le résumé optique).

### Modifié

- **Page commande (admin)** :
  - résumé en **2 colonnes** (œil gauche OS à gauche, œil droit OD à droite) ;
  - mode **same power** : **une seule colonne** et quantité partagée (ex. 5, pas 5 + 5) ;
  - QR plus grands et espacés pour le scan ;
  - tableau articles : masquage miniature, **prix unitaire**, **quantité**, taxes (lignes 100 % optiques) — colonnes **Article** et **Total** uniquement.
- Normalisation du payload **`same_power`** à l’enregistrement commande et à l’affichage (quantités `qty_left` / `qty_right` miroir).
- Panier : plus d’affichage texte « Internal SKUs » / « Eye quantities » sous le produit.
- CSS chargé sur **panier et checkout** (pas seulement le panier).
- Compatibilité **Flatsome** : pleine largeur des colonnes œil dans `product-name`, grille `dl.variation` neutralisée pour le résumé optique.

### Technique

- Hooks : `admin_body_class`, template `cart/cart-item-data.php` via `WC_Optic_Frontend::locate_template`.
- Réponse AJAX `wc_optic_preview_sku` enrichie avec `qr_html`.

## [1.0.0] — version initiale

- Type de produit WooCommerce **Optic Product**.
- Catalogue global (sections, marques, puissances SPH/CYL/AXIS/ADD, etc.).
- Produits internes par combinaison de puissances, SKU dynamique, stock par enfant.
- Prescription client, panier bi-œil, tarification par œil, import XLSX, WPML.
