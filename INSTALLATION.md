# Installation sur l'ordinateur du client (Windows + Laragon)

L'ordinateur du dépôt sert de serveur. Les utilisateurs ouvrent simplement le navigateur sur `http://depot.test` (ou l'adresse IP de l'ordinateur pour les autres postes du même Wi-Fi). **Aucune connexion internet n'est nécessaire.**

## 1. Préparer le projet (sur votre ordinateur de développeur)

```bash
composer create-project laravel/laravel depot
cd depot
composer require spatie/laravel-permission spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
npm install alpinejs chart.js
```

Copier par-dessus le contenu de ce dossier (`app/`, `bootstrap/app.php`, `database/`, `resources/`, `routes/`, `tests/`).

Dans `config/app.php` :

```php
'timezone' => 'America/Port-au-Prince',
'locale' => 'fr',
```

Dans `config/services.php`, ajouter :

```php
'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
```

Dans `resources/css/app.css` (Tailwind 4), vérifier que les vues sont scannées :

```css
@import 'tailwindcss';
@source '../views';
@plugin '@tailwindcss/forms';   /* npm install -D @tailwindcss/forms */
```

Construire les fichiers CSS/JS (ils seront servis en local, sans CDN) :

```bash
npm run build
php artisan test        # tous les tests doivent passer
```

## 2. Installer Laragon sur l'ordinateur du dépôt

1. Télécharger **Laragon Full** (laragon.org) et l'installer dans `C:\laragon`.
2. Copier le projet (avec `vendor/` et `public/build/`) dans `C:\laragon\www\depot`.
3. Démarrer Laragon → **Start All**. Laragon crée automatiquement l'adresse `http://depot.test`.
4. Créer la base : Laragon → **Database** → nouvelle base `depot` (utf8mb4).
5. Copier `.env.example` en `.env` et régler :

```env
APP_NAME="Gestion Stock"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://depot.test
DB_CONNECTION=mysql
DB_DATABASE=depot
DB_USERNAME=root
DB_PASSWORD=
SESSION_LIFETIME=600
MYSQLDUMP_PATH="C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"
```

(adapter le chemin de `mysqldump.exe` à la version installée).

6. Ouvrir le **Terminal** de Laragon dans le dossier du projet :

```bash
php artisan key:generate
php artisan migrate --seed          # installation propre
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pour une **démonstration** au client (30 jours de ventes fictives) :

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

Comptes : `admin@depot.local` / `ChangeMoi@2026` · `marie@depot.local`, `rose@depot.local`, `jean@depot.local` / `Demo@2026`.
**Avant la mise en service réelle**, refaire `php artisan migrate:fresh --seed` pour repartir à zéro.

## 3. Démarrage automatique

- Laragon → Préférences → cocher **Run Laragon when Windows starts** et **Start All automatically**.
- Créer un raccourci sur le bureau vers `http://depot.test` (icône du dépôt), ou mettre cette adresse comme page d'accueil du navigateur.
- Dans le navigateur, **autoriser les fenêtres pop-up** pour `depot.test` (impression automatique des reçus).

## 4. Sauvegardes automatiques (IMPORTANT)

Les sauvegardes sont planifiées à 13 h 00 et 19 h 30. Windows doit lancer le planificateur Laravel chaque minute :

1. Ouvrir le **Planificateur de tâches** Windows → Créer une tâche.
2. Déclencheur : **À l'ouverture de session**, répéter toutes les **1 minute** indéfiniment.
3. Action : programme `C:\laragon\bin\php\php-8.x\php.exe`, arguments `artisan schedule:run`, démarrer dans `C:\laragon\www\depot`.
4. Cocher « Exécuter même si l'utilisateur n'est pas connecté » si possible.

Dans **Paramètres → Sauvegarde**, indiquer un dossier de copie externe : clé USB (`E:\Sauvegardes`) ou dossier Google Drive / OneDrive synchronisé. Vérifier régulièrement la page **Sauvegardes**.

**Restaurer** : décompresser le zip, importer `base_….sql` avec HeidiSQL (Laragon → Database), copier le dossier `fichiers` dans `storage/app/public`.

## 5. Matériel conseillé

- Imprimante thermique **80 mm** (USB) installée comme imprimante par défaut, marges à 0 dans le navigateur.
- Lecteur de **code-barres USB** (il « tape » le code puis Entrée : rien à configurer).
- **Onduleur (UPS)** : une coupure brutale de courant peut abîmer la base de données.

## 6. Plusieurs postes (facultatif)

Sur le même Wi-Fi, un deuxième ordinateur ou une tablette peut ouvrir `http://<IP-du-serveur>` (ex. `http://192.168.1.10`). Réserver l'adresse IP du serveur dans la box et autoriser le port 80 dans le pare-feu Windows.

## 7. Mises à jour

```bash
git pull            # ou copier les nouveaux fichiers
composer install --no-dev
php artisan migrate --force
npm run build
php artisan optimize:clear && php artisan optimize
```

Toujours faire une sauvegarde avant une mise à jour.
