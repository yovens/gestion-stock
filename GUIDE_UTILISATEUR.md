# Guide d'utilisation

Ouvrir le navigateur sur `http://depot.test` et se connecter avec son identifiant. Chacun arrive directement sur son écran : la **caisse** pour les caissiers, le **tableau de bord** pour l'administrateur et la secrétaire.

---

## Caissier

### Début de journée
1. Se connecter → l'écran **Ouvrir la caisse** s'affiche.
2. Choisir sa caisse, compter le **fond de caisse** (HTG et USD) → **Ouvrir et commencer**.

### Vendre
- **Scanner** le code-barres : l'article s'ajoute tout seul. Le code d'une caisse ou d'un sac ajoute la caisse ou le sac entier.
- Sans code-barres : taper le nom dans la recherche (**F2**) ou toucher le produit. Les boutons « + Caisse », « + Sac » ajoutent le conditionnement.
- Dans le panier : changer l'unité (bouteille / caisse…), la quantité, une remise (limitée par l'administrateur).
- Le badge **GROS** apparaît quand le prix de gros s'applique (client « gros » ou quantité suffisante).
- Une ligne **rouge** = pas assez de stock : corriger la quantité.
- **Client** : toucher « Client comptoir » pour choisir un client (obligatoire pour une vente à crédit).

### Encaisser (F4)
- Saisir le montant reçu ou toucher un montant rapide (500, 1 000…). La **monnaie à rendre** s'affiche.
- Paiement en dollars : choisir **USD**, le système convertit au taux du jour.
- Plusieurs moyens : « + Autre moyen de paiement » (ex. une partie cash + une partie MonCash, avec le n° de transaction).
- Reste non payé : il va **sur le compte du client** (si l'administrateur lui a accordé du crédit).
- **Valider** (ou Entrée) → le reçu s'imprime.

### Mettre une vente en attente (F8)
Le client a oublié quelque chose ? **F8** met la vente de côté. Bouton **Attente** pour la reprendre.

### Paiement d'un crédit
Lien **Paiement de crédit** en haut de la caisse → choisir le client → **Encaisser un paiement** → le reçu s'imprime. Les espèces sont comptées dans votre caisse.

### Fin de journée
**Fermer la caisse** → compter tout l'argent du tiroir (fond compris) → **Fermer**. Le rapport s'imprime avec l'écart éventuel : le signer et le remettre au responsable.

---

## Secrétaire

- **Produits** : ajouter les produits (nom, code, unité de base, prix, conditionnements, code-barres, stock minimum). Les prix d'un produit existant ne peuvent être changés que par l'administrateur.
- **Réceptions** : à chaque livraison, enregistrer ce qui est arrivé avec le **n° de lot** et la **date d'expiration**. Le stock se met à jour immédiatement.
- **Bons de commande** : « Commander le stock bas » prépare la commande de tout ce qui manque. Imprimer et envoyer au fournisseur.
- **Pertes & ajustements** : déclarer la casse, les produits abîmés ou expirés. L'administrateur approuve.
- **Inventaires** : compter le stock réel (par catégorie). Imprimer la feuille de comptage, saisir les quantités, enregistrer.
- **Expirations** : liste des lots à vendre en priorité ou à retirer.
- **Clients** : créer les fiches clients, imprimer les relevés de compte.

---

## Administrateur

### Chaque jour
- **Tableau de bord** : ventes, bénéfice, alertes (rupture, stock bas, expirations), caisses ouvertes, clients qui doivent.
- **Pertes & ajustements** : approuver ou refuser les déclarations (le menu indique combien attendent).
- **Rapports → Sessions de caisse** : vérifier les écarts de caisse.

### Gestion
- **Ventes** : rechercher une vente, voir les lots sortis, **annuler** (raison obligatoire) ou faire un **retour** (remboursement en espèces ou déduction du compte client, remise en stock ou non).
- **Clients** : accorder le crédit et fixer la **limite**. La colonne « Depuis » montre l'ancienneté de la dette.
- **Fournisseurs** : enregistrer les paiements (ils soldent automatiquement les factures les plus anciennes). Les factures en retard sont signalées.
- **Dépenses** : électricité, carburant, transport… (cocher « payé avec l'argent de ma caisse » si l'argent sort du tiroir).
- **Employés** : fiches, salaires, avances (déduites automatiquement du reste à payer du mois).
- **Rapports** : ventes par jour / produit / catégorie / caissier, compte de résultat (bénéfice net), valeur du stock, crédit clients par ancienneté, dettes fournisseurs, dépenses, pertes. Bouton **Imprimer / PDF** ou **Exporter Excel**.

### Administration
- **Utilisateurs** : créer les comptes (rôle Administrateur, Secrétaire ou Caissier), désactiver un employé qui part (il est déconnecté immédiatement).
- **Paramètres** : nom et logo du dépôt (sur les reçus), **taux du dollar**, remise maximale des caissiers, alerte d'expiration, caisses, dossier de sauvegarde.
- **Journal d'activité** : qui a changé un prix, annulé une vente, modifié un paramètre.
- **Sauvegardes** : vérifier qu'il y a une sauvegarde récente ; « Sauvegarder maintenant » avant toute opération importante.

---

## Questions fréquentes

**Le reçu ne s'imprime pas.** Autoriser les pop-ups pour le site dans le navigateur ; vérifier que l'imprimante 80 mm est l'imprimante par défaut.

**« Stock insuffisant » alors que le produit est sur l'étagère.** Le produit n'a pas été réceptionné dans le système, ou le lot est expiré (la caisse refuse de vendre un produit expiré). Faire une réception ou un ajustement.

**Erreur dans une vente.** L'administrateur l'annule depuis **Ventes** ; le stock est remis automatiquement. Rendre l'argent au client.

**Le taux du dollar a changé.** Paramètres → Taux du dollar → saisir le nouveau taux.

**Mot de passe oublié.** L'administrateur le change dans **Utilisateurs → Modifier**.
