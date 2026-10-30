# Bons Points

Suivi éducatif d'un enfant : un équipement par enfant, des comportements qui rapportent ou retirent des points, un score par semaine de garde, des paliers de récompense, un historique et un bilan hebdomadaire.

## Configuration
- **Équipement** : prénom, semaines de garde (paires / impaires / toutes), blocage des points hors garde, commande de notification (type message), bilan automatique (jour + heure).
- **Comportements** : libellé + points (positif ou négatif). Chaque ligne crée une commande action.
- **Paliers** : récompense + points requis sur le score de la semaine.

## Commandes
Infos : Score semaine, une info binaire par palier (1 = atteint cette semaine), Score semaine précédente, Score total, Palier atteint, Palier semaine précédente, Prochain palier, Points manquants, Dernier événement, Bilan, Suivi actif, Présence forcée.
Actions : une par comportement, Annuler le dernier, Ajuster les points (message : titre = points, message = commentaire), Présence forcée On/Off, Remise à zéro semaine, Envoyer le bilan.

## Fonctionnement des semaines
Le score repart à 0 le lundi de chaque semaine de garde ; l'ancien est archivé. Hors garde, les points sont refusés sauf avec la présence forcée (désactivée automatiquement au changement de semaine). Le total ne repart jamais à 0.

Données : `data/bonsPoints/<id>.json` (conservé lors des mises à jour, inclus dans les sauvegardes Jeedom).
