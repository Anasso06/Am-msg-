# AM MSG — Première version sans base de données

Messagerie web simple utilisant PHP et des fichiers JSON. Elle est conçue pour être migrée plus tard vers MySQL en conservant l'interface.

## Fonctionnalités
- Création de compte et connexion
- Mots de passe hachés avec `password_hash`
- Conversations privées entre deux comptes
- Historique stocké dans un fichier JSON par conversation
- Actualisation automatique des messages toutes les 5 secondes
- Interface responsive pour téléphone et ordinateur

## Prérequis
- Hébergement web avec PHP 8.1 ou plus récent
- Extension PHP `mbstring`
- Dossiers/fichiers accessibles en écriture par PHP (`storage/`)
- HTTPS activé en production

## Installation sur un hébergement
1. Téléverser le contenu du dossier AM_MSG dans le répertoire web (souvent `public_html`).
2. Vérifier que `storage/` et `storage/conversations/` sont accessibles en écriture par PHP.
3. Ouvrir `https://ton-domaine.example/`.
4. Créer deux comptes de test dans deux navigateurs/appareils différents pour essayer les conversations.

**Important :** le dossier `storage/` contient les comptes et messages. Il doit rester privé et ne doit jamais être exposé publiquement. Le fichier `.htaccess` bloque son accès sur Apache. Pour un hébergement Nginx ou une autre configuration, placer `storage` en dehors du dossier public ou configurer une règle d'interdiction équivalente.

## Test local
Double-cliquer sur `index.html` ne suffit pas pour faire fonctionner les comptes et les conversations : l'API nécessite PHP. Si PHP est installé, ouvrir un terminal dans ce dossier et lancer :

```bash
php -S localhost:8000
```

Puis visiter `http://localhost:8000`.

## Limites de cette première version
- Ce prototype n'inclut pas encore récupération de mot de passe, modération, pièces jointes, notifications push ou chiffrement de bout en bout.
- Les fichiers JSON conviennent à un petit prototype. Pour une utilisation réelle avec beaucoup d'utilisateurs, préférer MySQL.
- Avant une mise en production, ajouter notamment une protection CSRF, des limites anti-spam, une politique de sauvegarde et des contrôles de sécurité adaptés.

## Préparer la migration MySQL
Les accès aux données sont centralisés dans `api.php`. Lors de la migration, remplacer les fonctions de lecture/écriture JSON par des requêtes PDO, puis importer les utilisateurs et conversations. Ne jamais stocker les messages dans le dépôt GitHub.
