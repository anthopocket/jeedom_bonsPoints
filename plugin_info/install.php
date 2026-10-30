<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../../core/php/core.inc.php';

function bonsPoints_install() {
    bonsPoints::dataDir();
}

function bonsPoints_update() {
    bonsPoints::dataDir();
    foreach (eqLogic::byType('bonsPoints') as $eqLogic) {
        try {
            $eqLogic->save();
        } catch (Exception $e) {
            log::add('bonsPoints', 'error', 'Mise à jour ' . $eqLogic->getHumanName() . ' : ' . $e->getMessage());
        }
    }
}

function bonsPoints_remove() {
    // L'historique (data/bonsPoints) est volontairement conservé.
}
