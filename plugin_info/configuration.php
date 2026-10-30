<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <div class="alert alert-info">
            {{Aucun réglage global : tout se configure dans chaque équipement (un équipement par enfant).}}<br/>
            {{L'historique est stocké dans le dossier data/bonsPoints de Jeedom (inclus dans les sauvegardes, conservé lors des mises à jour du plugin).}}
        </div>
    </fieldset>
</form>
