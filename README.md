# Gestion de stock — Dépôt alimentaire (Laravel)

Application complète pour un dépôt qui vend **en gros et au détail** : caisse avec lecteur de code-barres, stock par lots avec dates d'expiration, achats et fournisseurs, clients à crédit, dépenses, employés, rapports et sauvegardes. Fonctionne **hors ligne** sur l'ordinateur du dépôt.

- **Installation** : voir [`INSTALLATION.md`](INSTALLATION.md)
- **Mode d'emploi** (caissier, secrétaire, administrateur) : voir [`GUIDE_UTILISATEUR.md`](GUIDE_UTILISATEUR.md)
- **Architecture, base de données, règles** : voir [`SCHEMA.md`](SCHEMA.md)

## Démarrage rapide (démo)

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
npm run build && php artisan serve
```

| Compte | Mot de passe | Rôle |
|---|---|---|
| admin@depot.local | ChangeMoi@2026 | Administrateur |
| marie@depot.local | Demo@2026 | Secrétaire |
| rose@depot.local / jean@depot.local | Demo@2026 | Caissiers |

## Rôles

| | Administrateur | Secrétaire | Caissier |
|---|:-:|:-:|:-:|
| Caisse, ventes, reçus | ✓ | | ✓ |
| Encaisser un crédit client | ✓ | ✓ | ✓ |
| Produits, réceptions, bons de commande, inventaires | ✓ | ✓ | |
| Modifier les prix, accorder du crédit | ✓ | | |
| Approuver pertes/ajustements, valider inventaires | ✓ | | |
| Payer les fournisseurs, salaires | ✓ | | |
| Bénéfices, compte de résultat | ✓ | | |
| Annuler une vente, retours | ✓ | | |
| Utilisateurs, paramètres, journal, sauvegardes | ✓ | | |

## Pile technique

Laravel 11/12 · MySQL (ou SQLite) · Blade + Alpine.js + Tailwind CSS 4 · Chart.js · spatie/laravel-permission · spatie/laravel-activitylog. Tout est compilé avec Vite : aucun CDN, aucune connexion internet nécessaire.
