# 🌍 Bilan Carbone Collège E3D

> **Outil de diagnostic environnemental pour les éco-délégués et les établissements scolaires.**

Ce projet est une application web légère conçue pour permettre aux collèges de réaliser leur propre bilan carbone. Il transforme les données d'activité (consommations d'énergie, repas, trajets) en émissions de gaz à effet de serre (**kgCO₂e**) en s'appuyant sur les facteurs officiels de la **Base Empreinte® de l'ADEME**.

---

## ✨ Points forts

* **Pédagogie E3D** : Permet d'impliquer les élèves dans la démarche de labellisation de l'établissement.
* **Accès multi-utilisateurs** : Des comptes spécifiques pour les éco-délégués (Transport, Cantine, Déchets, etc.) et les professeurs.
* **42 Postes d'émissions** : Pré-configurés selon la méthodologie ADEME.
* **Visualisation de données** : Graphiques dynamiques et équivalences concrètes (ex: trajets Paris-New York, bouteilles plastique).
* **Expertise IA** : Générateur de "Prompt" structuré pour analyser les résultats avec une intelligence artificielle (ChatGPT, Gemini).

---

## 🚀 Installation

L'application ne nécessite pas de base de données externe (MySQL/PostgreSQL), elle utilise **SQLite** pour une simplicité maximale.

1.  Déposez les fichiers `index.php` et `install.php` sur votre serveur web (Hébergement mutualisé, NAS, ou serveur local avec PHP 8.0+).
2.  Rendez-vous sur `votre-domaine.fr/install.php`.
3.  L'installateur crée automatiquement la base de données `bilan_carbone.db` et les comptes par défaut.
4.  **⚠️ IMPORTANT : Supprimez le fichier `install.php` après l'installation pour sécuriser l'application.**

### Identifiants par défaut :
* **Admin** : `admin` / `admin123`
* **Élève Transport** : `eleve1` / `eleve123`
* **Élève Cantine** : `eleve2` / `eleve456`
* **Professeur Énergie** : `prof1` / `prof123`

---

## 📊 Thématiques couvertes

Le bilan est divisé en 8 pôles de données :
* 🔌 **Énergie** (Électricité, Gaz, Fioul...)
* 🚌 **Transport & Déplacements** (Domicile-travail, sorties, voyages...)
* 🥗 **Alimentation & Restauration** (Type de viande, repas végétariens, gaspillage...)
* ♻️ **Déchets** (Tri, compost, DEEE...)
* 💻 **Immobilisations** (Fabrication du matériel informatique, mobilier...)
* 🛒 **Fournitures & Achats** (Papier, produits d'entretien...)
* 🏗️ **Bâtiment & Travaux** (Rénovations, espaces verts...)
* 📡 **Numérique** (Usage des serveurs, streaming...)

---

## 🤖 Analyse par Intelligence Artificielle

Une fois les données saisies, l'interface administrateur propose un **Prompt IA** pré-rempli. Ce texte contient toutes vos statistiques et demande à l'IA de générer :
1.  Un diagnostic des points critiques (points chauds).
2.  Un plan d'action concret et chiffré pour l'année suivante.
3.  Une structure de présentation pour le **Label E3D Niveau 3**.

---

## 🛠️ Caractéristiques techniques

* **Langage** : PHP 8.0+.
* **Base de données** : SQLite 3.
* **Framework CSS** : Pico.css (léger et responsive).
* **Librairie Graphique** : Chart.js.
* **Sources des données** : Base Empreinte® ADEME 2024.

---

## ⚖️ Licence

Ce projet est distribué sous licence MIT. Les facteurs d'émissions restent la propriété de l'ADEME via la Base Empreinte®.
