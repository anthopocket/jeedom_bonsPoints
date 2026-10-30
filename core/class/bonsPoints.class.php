<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class bonsPoints extends eqLogic {

    const MAX_EVENTS = 5000;

    /* ======================================================================
     * Valeurs par défaut
     * ==================================================================== */

    public static function defaultBehaviours() {
        $list = array(
            // --- Matin
            array('Se lève sans traîner au réveil', 1),
            array('S\'habille seul et rapidement', 1),
            array('Prêt à l\'heure pour l\'école', 2),
            array('Se brosse les dents sans qu\'on le rappelle', 1),
            // --- École et devoirs
            array('Fait ses devoirs sans qu\'on insiste', 2),
            array('Prépare son cartable la veille', 1),
            array('Bon mot ou bonne note de la maîtresse', 2),
            array('Lit 15 minutes', 1),
            // --- Maison
            array('Range sa chambre', 1),
            array('Met ou débarrasse la table', 1),
            array('Range ses affaires en rentrant', 1),
            array('Met son linge sale au panier', 1),
            array('Aide spontanément sans qu\'on demande', 2),
            // --- Repas
            array('Goûte un nouvel aliment', 1),
            array('Mange correctement à table', 1),
            // --- Comportement
            array('Est poli', 1),
            array('Ne contredit pas', 1),
            array('Obéit du premier coup', 2),
            array('Dit la vérité, même quand c\'est difficile', 2),
            array('Reste calme au lieu de s\'énerver', 2),
            array('Est gentil ou partage', 1),
            array('Accepte de couper les écrans sans râler', 2),
            // --- Soir
            array('Va au bain dès que je le demande', 2),
            array('Va au lit à l\'heure', 2),
            // --- Sport
            array('Prépare son sac de rugby tout seul', 1),
            array('Bon esprit à l\'entraînement ou au match', 1),
            // --- Points en moins
            array('Mot de la maîtresse', -4),
            array('Refuse d\'aller au bain', -2),
            array('Se couche en retard ou se relève', -2),
            array('Contredit ou répond', -1),
            array('Est impoli ou dit des gros mots', -1),
            array('Ment', -2),
            array('Crie ou fait une colère', -1),
            array('Refuse de couper les écrans', -2),
            array('Ne range pas après avoir joué', -1),
            array('Doit être appelé 3 fois', -1),
            array('Est méchant ou tape', -2),
        );
        $result = array();
        $i = 0;
        foreach ($list as $b) {
            $result[] = array('id' => 'b' . (time() + $i++), 'name' => $b[0], 'points' => $b[1]);
        }
        return $result;
    }

    public static function defaultPaliers() {
        return array(
            array('id' => 'p' . time() . '1', 'name' => 'Dessert au choix', 'points' => 10),
            array('id' => 'p' . time() . '2', 'name' => 'Sortie', 'points' => 20),
            array('id' => 'p' . time() . '3', 'name' => 'Super récompense', 'points' => 30),
        );
    }

    /* ======================================================================
     * Calcul des semaines (garde alternée)
     * ==================================================================== */

    /** Lundi 00:00 de la semaine ISO contenant $ts */
    public static function mondayOf($ts) {
        $d = new DateTime('@' . $ts);
        $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $d->setISODate((int) $d->format('o'), (int) $d->format('W'), 1);
        $d->setTime(0, 0, 0);
        return $d->getTimestamp();
    }

    /** La semaine contenant $ts est-elle une semaine de garde ? */
    public static function isCustodyWeek($mode, $ts) {
        if ($mode == 'toutes') {
            return true;
        }
        $want = ($mode == 'impaires') ? 1 : 0;
        return ((int) date('W', $ts)) % 2 == $want;
    }

    /**
     * Début de la période en cours : lundi de la dernière semaine de garde.
     * Mode "paires" : une période = semaine paire + semaine impaire suivante
     * (les jours "forcés" en semaine impaire comptent dans la même période).
     */
    public static function periodStartFor($mode, $ts) {
        $monday = self::mondayOf($ts);
        if ($mode == 'toutes') {
            return $monday;
        }
        for ($i = 0; $i < 4; $i++) {
            if (self::isCustodyWeek($mode, $monday)) {
                return $monday;
            }
            $monday = strtotime('-7 days', $monday);
        }
        return self::mondayOf($ts);
    }

    public function getMode() {
        return $this->getConfiguration('garde', 'paires');
    }

    public function periodStart($ts = null) {
        return self::periodStartFor($this->getMode(), ($ts === null) ? time() : $ts);
    }

    /* ======================================================================
     * Stockage (data/bonsPoints/<id>.json)
     * ==================================================================== */

    public static function dataDir() {
        $dir = __DIR__ . '/../../../../data/bonsPoints';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function dataFile() {
        return self::dataDir() . '/' . $this->getId() . '.json';
    }

    public function loadData() {
        $default = array(
            'period' => null,
            'score' => 0,
            'total' => 0,
            'prevScore' => 0,
            'prevPalier' => '',
            'presence' => 0,
            'bilanSentFor' => null,
            'events' => array(),
            'weeks' => array(),
        );
        $file = $this->dataFile();
        if (!file_exists($file)) {
            return $default;
        }
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            log::add(__CLASS__, 'error', 'Fichier de données illisible : ' . $file);
            return $default;
        }
        return array_merge($default, $data);
    }

    public function saveData($data) {
        if (count($data['events']) > self::MAX_EVENTS) {
            $data['events'] = array_slice($data['events'], -self::MAX_EVENTS);
        }
        $file = $this->dataFile();
        if (file_put_contents($file . '.tmp', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new Exception(__('Impossible d\'écrire', __FILE__) . ' ' . $file);
        }
        rename($file . '.tmp', $file);
    }

    /* ======================================================================
     * Comportements / paliers
     * ==================================================================== */

    public function getBehaviours() {
        $list = $this->getConfiguration('behaviours', array());
        return is_array($list) ? $list : array();
    }

    public function getBehaviour($id) {
        foreach ($this->getBehaviours() as $b) {
            if ($b['id'] == $id) {
                return $b;
            }
        }
        return null;
    }

    public function getPaliers() {
        $list = $this->getConfiguration('paliers', array());
        if (!is_array($list)) {
            return array();
        }
        usort($list, function ($a, $b) {
            return $a['points'] - $b['points'];
        });
        return $list;
    }

    /** Palier positif (>= 0) = récompense ; palier négatif = sanction */
    public static function isSanction($p) {
        return $p['points'] < 0;
    }

    /** Récompense : atteinte si score >= points. Sanction : atteinte si score <= points */
    public static function isReached($p, $score) {
        return self::isSanction($p) ? ($score <= $p['points']) : ($score >= $p['points']);
    }

    /** Récompense la plus haute atteinte (ou null) */
    public function palierFor($score) {
        $found = null;
        foreach ($this->getPaliers() as $p) {
            if (!self::isSanction($p) && $score >= $p['points']) {
                $found = $p;
            }
        }
        return $found;
    }

    /** Prochaine récompense non atteinte (ou null) */
    public function nextPalier($score) {
        foreach ($this->getPaliers() as $p) {
            if (!self::isSanction($p) && $score < $p['points']) {
                return $p;
            }
        }
        return null;
    }

    /** Sanction la plus sévère atteinte (ou null) */
    public function sanctionFor($score) {
        foreach ($this->getPaliers() as $p) { // tri croissant : -20, -10, -5…
            if (self::isSanction($p) && $score <= $p['points']) {
                return $p;
            }
        }
        return null;
    }

    /** Jeedom supprime les apostrophes droites des noms de commandes */
    public static function cmdName($name) {
        return str_replace("'", '’', $name);
    }

    public static function fmtPts($p) {
        return ($p > 0 ? '+' : '') . $p;
    }

    /* ======================================================================
     * Logique principale
     * ==================================================================== */

    /** Le suivi est-il actif maintenant (semaine de garde ou présence forcée) ? */
    public function isActive($data = null) {
        if ($data === null) {
            $data = $this->loadData();
        }
        if ($this->getConfiguration('bloquerHorsGarde', 1) != 1) {
            return true;
        }
        return self::isCustodyWeek($this->getMode(), time()) || $data['presence'] == 1;
    }

    /** Changement de période : archive la semaine écoulée et remet le score à 0 */
    public function checkPeriod() {
        $data = $this->loadData();
        $key = date('Y-m-d', $this->periodStart());
        if ($data['period'] === null) {
            $data['period'] = $key;
            $this->saveData($data);
            return;
        }
        if ($data['period'] == $key) {
            return;
        }
        $palier = $this->palierFor($data['score']);
        $sanction = $this->sanctionFor($data['score']);
        $data['weeks'][] = array(
            'start' => $data['period'],
            'end' => date('Y-m-d', strtotime($key . ' -1 day')),
            'score' => $data['score'],
            'palier' => ($palier === null) ? '' : $palier['name'],
            'sanction' => ($sanction === null) ? '' : $sanction['name'],
        );
        $data['prevScore'] = $data['score'];
        $data['prevPalier'] = ($palier === null) ? '' : $palier['name'];
        log::add(__CLASS__, 'info', $this->getHumanName() . ' nouvelle période ' . $key . ' (semaine précédente : ' . $data['score'] . ' pts)');
        $data['score'] = 0;
        $data['period'] = $key;
        $data['presence'] = 0;
        $this->saveData($data);
        $this->refreshCmds($data);
    }

    public function addPoints($points, $label, $comment = '') {
        $points = (int) $points;
        $this->checkPeriod();
        $data = $this->loadData();
        if (!$this->isActive($data)) {
            log::add(__CLASS__, 'info', $this->getHumanName() . ' hors semaine de garde, ignoré : ' . $label);
            $this->checkAndUpdateCmd('dernier', date('d/m H:i') . ' — ' . __('Ignoré (hors garde)', __FILE__) . ' : ' . $label);
            return false;
        }
        $oldPalier = $this->palierFor($data['score']);
        $oldSanction = $this->sanctionFor($data['score']);
        $data['score'] += $points;
        $data['total'] += $points;
        $data['events'][] = array(
            'ts' => time(),
            'period' => $data['period'],
            'label' => $label,
            'points' => $points,
            'comment' => $comment,
        );
        $this->saveData($data);
        log::add(__CLASS__, 'info', $this->getHumanName() . ' ' . self::fmtPts($points) . ' ' . $label . ($comment != '' ? ' (' . $comment . ')' : ''));
        $this->refreshCmds($data);

        if ($this->getConfiguration('notifPalier', 0) == 1) {
            $newPalier = $this->palierFor($data['score']);
            if ($newPalier !== null && ($oldPalier === null || $newPalier['points'] > $oldPalier['points'])) {
                $this->notify(
                    $this->getName() . ' : ' . __('palier atteint', __FILE__),
                    '🎉 ' . $this->getName() . ' a atteint le palier « ' . $newPalier['name'] . ' » (' . $data['score'] . ' pts)'
                );
            }
            $newSanction = $this->sanctionFor($data['score']);
            if ($newSanction !== null && ($oldSanction === null || $newSanction['points'] < $oldSanction['points'])) {
                $this->notify(
                    $this->getName() . ' : ' . __('sanction', __FILE__),
                    '⚠️ ' . $this->getName() . ' est descendu à ' . $data['score'] . ' pts : sanction « ' . $newSanction['name'] . ' »'
                );
            }
        }
        return true;
    }

    /** Annule le dernier point de la période en cours */
    public function undoLast() {
        $this->checkPeriod();
        $data = $this->loadData();
        $last = end($data['events']);
        if ($last === false || $last['period'] != $data['period']) {
            $this->checkAndUpdateCmd('dernier', date('d/m H:i') . ' — ' . __('Rien à annuler', __FILE__));
            return;
        }
        array_pop($data['events']);
        $data['score'] -= $last['points'];
        $data['total'] -= $last['points'];
        $this->saveData($data);
        log::add(__CLASS__, 'info', $this->getHumanName() . ' annulation : ' . $last['label'] . ' (' . self::fmtPts($last['points']) . ')');
        $this->refreshCmds($data);
        $this->checkAndUpdateCmd('dernier', date('d/m H:i') . ' — ' . __('Annulé', __FILE__) . ' : ' . $last['label'] . ' (' . self::fmtPts($last['points']) . ')');
    }

    /** Remise à zéro de la semaine : tracée comme un événement (le total est corrigé aussi) */
    public function resetWeek() {
        $this->checkPeriod();
        $data = $this->loadData();
        if ($data['score'] == 0) {
            return;
        }
        $pts = -$data['score'];
        $data['total'] += $pts;
        $data['score'] = 0;
        $data['events'][] = array('ts' => time(), 'period' => $data['period'], 'label' => __('Remise à zéro', __FILE__), 'points' => $pts, 'comment' => '');
        $this->saveData($data);
        $this->refreshCmds($data);
    }

    public function setPresence($value) {
        $this->checkPeriod();
        $data = $this->loadData();
        $data['presence'] = $value ? 1 : 0;
        $this->saveData($data);
        $this->refreshCmds($data);
    }

    /** Met à jour toutes les commandes info depuis les données */
    public function refreshCmds($data = null) {
        if ($data === null) {
            $data = $this->loadData();
        }
        $palier = $this->palierFor($data['score']);
        $next = $this->nextPalier($data['score']);
        $sanction = $this->sanctionFor($data['score']);
        $this->checkAndUpdateCmd('sanction_atteinte', ($sanction === null) ? __('Aucune', __FILE__) : $sanction['name']);
        $this->checkAndUpdateCmd('score_semaine', $data['score']);
        $this->checkAndUpdateCmd('score_semaine_prec', $data['prevScore']);
        $this->checkAndUpdateCmd('score_total', $data['total']);
        $this->checkAndUpdateCmd('palier_atteint', ($palier === null) ? __('Aucun', __FILE__) : $palier['name']);
        $this->checkAndUpdateCmd('palier_prec', ($data['prevPalier'] == '') ? __('Aucun', __FILE__) : $data['prevPalier']);
        $this->checkAndUpdateCmd('prochain_palier', ($next === null) ? __('Tous atteints', __FILE__) : $next['name'] . ' (' . $next['points'] . ' pts)');
        $this->checkAndUpdateCmd('points_manquants', ($next === null) ? 0 : $next['points'] - $data['score']);
        foreach ($this->getPaliers() as $p) {
            if (isset($p['id'])) {
                $this->checkAndUpdateCmd('palier_' . $p['id'], self::isReached($p, $data['score']) ? 1 : 0);
            }
        }
        $this->checkAndUpdateCmd('presence', $data['presence']);
        $this->checkAndUpdateCmd('actif', $this->isActive($data) ? 1 : 0);
        $last = end($data['events']);
        if ($last !== false) {
            $txt = date('d/m H:i', $last['ts']) . ' — ' . $last['label'] . ' (' . self::fmtPts($last['points']) . ')';
            if ($last['comment'] != '') {
                $txt .= ' : ' . $last['comment'];
            }
            $this->checkAndUpdateCmd('dernier', $txt);
        }
    }

    /* ======================================================================
     * Bilan
     * ==================================================================== */

    public function buildBilan($data = null) {
        if ($data === null) {
            $data = $this->loadData();
        }
        $plus = array();
        $moins = array();
        foreach ($data['events'] as $e) {
            if ($e['period'] != $data['period']) {
                continue;
            }
            if ($e['points'] > 0) {
                $plus[$e['label']] = (isset($plus[$e['label']]) ? $plus[$e['label']] : 0) + 1;
            } elseif ($e['points'] < 0) {
                $moins[$e['label']] = (isset($moins[$e['label']]) ? $moins[$e['label']] : 0) + 1;
            }
        }
        arsort($plus);
        arsort($moins);
        $fmt = function ($arr) {
            $out = array();
            foreach (array_slice($arr, 0, 3, true) as $label => $n) {
                $out[] = $label . ' ×' . $n;
            }
            return implode(', ', $out);
        };
        $start = strtotime($data['period']);
        $palier = $this->palierFor($data['score']);
        $sanction = $this->sanctionFor($data['score']);
        $lines = array();
        $lines[] = 'Bilan de ' . $this->getName() . ' — semaine du ' . date('d/m', $start) . ' au ' . date('d/m', strtotime('+6 days', $start));
        $lines[] = 'Score : ' . $data['score'] . ' pts (palier : ' . (($palier === null) ? 'aucun' : $palier['name']) . ')';
        if ($sanction !== null) {
            $lines[] = '⚠️ Sanction : ' . $sanction['name'];
        }
        if (count($plus) > 0) {
            $lines[] = '👍 Points forts : ' . $fmt($plus);
        }
        if (count($moins) > 0) {
            $lines[] = '👎 À travailler : ' . $fmt($moins);
        }
        $lines[] = 'Semaine précédente : ' . $data['prevScore'] . ' pts — Total : ' . $data['total'] . ' pts';
        return implode("\n", $lines);
    }

    public function sendBilan($markSent = false) {
        $this->checkPeriod();
        $data = $this->loadData();
        $txt = $this->buildBilan($data);
        $this->checkAndUpdateCmd('bilan', $txt);
        $this->notify(__('Bilan', __FILE__) . ' ' . $this->getName(), $txt);
        if ($markSent) {
            $data['bilanSentFor'] = $data['period'];
            $this->saveData($data);
        }
        log::add(__CLASS__, 'info', $this->getHumanName() . ' bilan envoyé');
    }

    /** Envoi automatique : jour J (1 = lundi … 7 = dimanche) de la semaine de garde, à l'heure choisie */
    public function checkBilan() {
        if ($this->getConfiguration('bilanEnable', 0) != 1) {
            return;
        }
        $data = $this->loadData();
        if ($data['period'] === null || $data['bilanSentFor'] == $data['period']) {
            return;
        }
        $day = max(1, min(7, (int) $this->getConfiguration('bilanDay', 7)));
        $hour = $this->getConfiguration('bilanHour', '19:00');
        if (!preg_match('/^\d{1,2}:\d{2}$/', $hour)) {
            $hour = '19:00';
        }
        $when = strtotime($data['period'] . ' +' . ($day - 1) . ' days ' . $hour);
        if (time() >= $when) {
            $this->sendBilan(true);
        }
    }

    /** Envoi via la commande message choisie dans l'équipement */
    public function notify($title, $message) {
        $cmdId = str_replace('#', '', $this->getConfiguration('notifCmd', ''));
        if ($cmdId == '') {
            return;
        }
        $cmd = cmd::byId($cmdId);
        if (!is_object($cmd)) {
            log::add(__CLASS__, 'error', $this->getHumanName() . ' commande de notification introuvable : ' . $cmdId);
            return;
        }
        try {
            $cmd->execCmd(array('title' => $title, 'message' => $message));
        } catch (Exception $e) {
            log::add(__CLASS__, 'error', $this->getHumanName() . ' notification : ' . $e->getMessage());
        }
    }

    /* ======================================================================
     * Cron
     * ==================================================================== */

    public static function cron5() {
        foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
            try {
                $eqLogic->checkPeriod();
                $eqLogic->checkAndUpdateCmd('actif', $eqLogic->isActive() ? 1 : 0);
                $eqLogic->checkBilan();
            } catch (Exception $e) {
                log::add(__CLASS__, 'error', $eqLogic->getHumanName() . ' cron : ' . $e->getMessage());
            }
        }
    }

    /* ======================================================================
     * Cycle de vie de l'équipement
     * ==================================================================== */

    public function preInsert() {
        if (count($this->getBehaviours()) == 0) {
            $this->setConfiguration('behaviours', self::defaultBehaviours());
        }
        if ($this->getConfiguration('paliers', '') === '') {
            $this->setConfiguration('paliers', self::defaultPaliers());
        }
        $this->setConfiguration('garde', 'paires');
        $this->setConfiguration('bloquerHorsGarde', 1);
        $this->setConfiguration('bilanDay', 7);
        $this->setConfiguration('bilanHour', '19:00');
        $this->setIsEnable(1);
        $this->setIsVisible(1);
    }

    public function preSave() {
        $clean = array();
        $i = 0;
        foreach ($this->getBehaviours() as $b) {
            $name = trim(isset($b['name']) ? $b['name'] : '');
            if ($name == '') {
                continue;
            }
            $id = (isset($b['id']) && $b['id'] != '') ? preg_replace('/[^a-zA-Z0-9_]/', '', $b['id']) : 'b' . (time() + $i);
            $i++;
            $clean[] = array('id' => $id, 'name' => $name, 'points' => (int) (isset($b['points']) ? $b['points'] : 0));
        }
        $this->setConfiguration('behaviours', $clean);

        $paliers = array();
        $raw = $this->getConfiguration('paliers', array());
        if (is_array($raw)) {
            foreach ($raw as $p) {
                $name = trim(isset($p['name']) ? $p['name'] : '');
                if ($name == '') {
                    continue;
                }
                $id = (isset($p['id']) && $p['id'] != '') ? preg_replace('/[^a-zA-Z0-9_]/', '', $p['id']) : 'p' . (time() + $i);
                $i++;
                $paliers[] = array('id' => $id, 'name' => $name, 'points' => (int) (isset($p['points']) ? $p['points'] : 0));
            }
        }
        usort($paliers, function ($a, $b) {
            return $a['points'] - $b['points'];
        });
        $this->setConfiguration('paliers', $paliers);
    }

    private function ensureCmd($logicalId, $name, $type, $subType, $order, $extra = array()) {
        $cmd = $this->getCmd(null, $logicalId);
        if (!is_object($cmd)) {
            $cmd = new bonsPointsCmd();
            $cmd->setLogicalId($logicalId);
            $cmd->setEqLogic_id($this->getId());
            $cmd->setName($name);
            $cmd->setOrder($order);
            if (isset($extra['historized'])) {
                $cmd->setIsHistorized(1);
            }
            if (isset($extra['unite'])) {
                $cmd->setUnite($extra['unite']);
            }
            if (isset($extra['hidden'])) {
                $cmd->setIsVisible(0);
            }
        }
        if (isset($extra['forceName'])) {
            $cmd->setName($name);
        }
        $cmd->setType($type);
        $cmd->setSubType($subType);
        if (isset($extra['value'])) {
            $cmd->setValue($extra['value']);
        }
        $cmd->save();
        return $cmd;
    }

    public function postSave() {
        $order = 0;
        // --- Infos
        $this->ensureCmd('score_semaine', 'Score semaine', 'info', 'numeric', $order++, array('historized' => 1, 'unite' => 'pts'));
        $this->ensureCmd('score_semaine_prec', 'Score semaine précédente', 'info', 'numeric', $order++, array('unite' => 'pts'));
        $this->ensureCmd('score_total', 'Score total', 'info', 'numeric', $order++, array('historized' => 1, 'unite' => 'pts'));
        $this->ensureCmd('palier_atteint', 'Palier atteint', 'info', 'string', $order++);
        $this->ensureCmd('palier_prec', 'Palier semaine précédente', 'info', 'string', $order++, array('hidden' => 1));
        $this->ensureCmd('sanction_atteinte', 'Sanction atteinte', 'info', 'string', $order++);
        $this->ensureCmd('prochain_palier', 'Prochain palier', 'info', 'string', $order++);
        $this->ensureCmd('points_manquants', 'Points manquants', 'info', 'numeric', $order++, array('unite' => 'pts'));
        $this->ensureCmd('dernier', 'Dernier événement', 'info', 'string', $order++);
        $this->ensureCmd('bilan', 'Bilan', 'info', 'string', $order++, array('hidden' => 1));
        $this->ensureCmd('actif', 'Suivi actif', 'info', 'binary', $order++, array('hidden' => 1));
        $presence = $this->ensureCmd('presence', 'Présence forcée', 'info', 'binary', $order++, array('hidden' => 1));

        // --- Paliers (une info binaire par palier : 1 = atteint cette semaine)
        $keepP = array();
        foreach ($this->getPaliers() as $p) {
            $lid = 'palier_' . $p['id'];
            $keepP[] = $lid;
            $label = (self::isSanction($p) ? 'Sanction ' : 'Palier ') . self::cmdName($p['name']) . ' (' . $p['points'] . ' pts)';
            $this->ensureCmd($lid, $label, 'info', 'binary', $order++, array('forceName' => 1, 'historized' => 1));
        }
        foreach ($this->getCmd('info') as $cmd) {
            $l = $cmd->getLogicalId();
            if (strpos($l, 'palier_') === 0 && !in_array($l, array('palier_atteint', 'palier_prec')) && !in_array($l, $keepP)) {
                $cmd->remove();
            }
        }

        // --- Comportements (une action par comportement)
        $keep = array();
        $usedNames = array();
        foreach ($this->getBehaviours() as $b) {
            $lid = 'behav_' . $b['id'];
            $keep[] = $lid;
            $name = self::cmdName($b['name']) . ' (' . self::fmtPts($b['points']) . ')';
            $base = $name;
            $n = 2;
            while (in_array(strtolower($name), $usedNames)) {
                $name = $base . ' ' . $n++;
            }
            $usedNames[] = strtolower($name);
            $this->ensureCmd($lid, $name, 'action', 'other', $order++, array('forceName' => 1));
        }
        foreach ($this->getCmd('action') as $cmd) {
            if (strpos($cmd->getLogicalId(), 'behav_') === 0 && !in_array($cmd->getLogicalId(), $keep)) {
                $cmd->remove();
            }
        }

        // --- Actions de gestion
        $this->ensureCmd('annuler', 'Annuler le dernier', 'action', 'other', $order++);
        $this->ensureCmd('ajuster', 'Ajuster les points', 'action', 'message', $order++, array('hidden' => 1));
        $this->ensureCmd('presence_on', 'Présence forcée On', 'action', 'other', $order++, array('hidden' => 1, 'value' => $presence->getId()));
        $this->ensureCmd('presence_off', 'Présence forcée Off', 'action', 'other', $order++, array('hidden' => 1, 'value' => $presence->getId()));
        $this->ensureCmd('raz_semaine', 'Remise à zéro semaine', 'action', 'other', $order++, array('hidden' => 1));
        $this->ensureCmd('bilan_envoyer', 'Envoyer le bilan', 'action', 'other', $order++, array('hidden' => 1));

        if ($this->getId() != '') {
            $this->checkPeriod();
            $this->refreshCmds();
        }
    }

    public function preRemove() {
        $file = $this->dataFile();
        if (file_exists($file)) {
            @rename($file, $file . '.supprime_' . date('Ymd_His'));
        }
    }
}

class bonsPointsCmd extends cmd {

    public function execute($_options = array()) {
        /** @var bonsPoints $eq */
        $eq = $this->getEqLogic();
        $lid = $this->getLogicalId();

        if (strpos($lid, 'behav_') === 0) {
            $b = $eq->getBehaviour(substr($lid, 6));
            if ($b === null) {
                throw new Exception(__('Comportement introuvable', __FILE__) . ' : ' . $lid);
            }
            $eq->addPoints($b['points'], $b['name']);
            return;
        }

        switch ($lid) {
            case 'annuler':
                $eq->undoLast();
                break;
            case 'ajuster':
                // Titre = nombre de points (ex. 3 ou -2), message = commentaire
                $title = isset($_options['title']) ? trim($_options['title']) : '';
                $message = isset($_options['message']) ? trim($_options['message']) : '';
                if (!preg_match('/^[+-]?\d+$/', $title)) {
                    // tolère "3 pour avoir aidé" dans le message
                    if (preg_match('/^([+-]?\d+)\s*(.*)$/s', $message, $m)) {
                        $title = $m[1];
                        $message = $m[2];
                    } else {
                        throw new Exception(__('Indiquez un nombre de points (ex. 3 ou -2) dans le titre', __FILE__));
                    }
                }
                $eq->addPoints((int) $title, __('Ajustement manuel', __FILE__), $message);
                break;
            case 'presence_on':
                $eq->setPresence(1);
                break;
            case 'presence_off':
                $eq->setPresence(0);
                break;
            case 'raz_semaine':
                $eq->resetWeek();
                break;
            case 'bilan_envoyer':
                $eq->sendBilan(false);
                break;
        }
    }
}
