<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    ajax::init();

    if (init('action') == 'getHistory') {
        $eqLogic = bonsPoints::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new Exception(__('Équipement introuvable', __FILE__) . ' : ' . init('id'));
        }
        $eqLogic->checkPeriod();
        $data = $eqLogic->loadData();
        $events = array_reverse(array_slice($data['events'], -500));
        foreach ($events as &$e) {
            $e['date'] = date('d/m/Y H:i', $e['ts']);
        }
        $palier = $eqLogic->palierFor($data['score']);
        $next = $eqLogic->nextPalier($data['score']);
        $sanction = $eqLogic->sanctionFor($data['score']);
        ajax::success(array(
            'period' => $data['period'],
            'score' => $data['score'],
            'total' => $data['total'],
            'prevScore' => $data['prevScore'],
            'palier' => ($palier === null) ? '' : $palier['name'],
            'sanction' => ($sanction === null) ? '' : $sanction['name'],
            'next' => ($next === null) ? '' : $next['name'] . ' (' . $next['points'] . ' pts)',
            'actif' => $eqLogic->isActive($data) ? 1 : 0,
            'presence' => $data['presence'],
            'events' => $events,
            'weeks' => array_reverse($data['weeks']),
        ));
    }

    if (init('action') == 'getDefaults') {
        ajax::success(array('behaviours' => bonsPoints::defaultBehaviours()));
    }

    throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
