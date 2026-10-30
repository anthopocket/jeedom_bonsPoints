<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('bonsPoints');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
    <!-- ===================== Page d'accueil ===================== -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoPrimary" data-action="add">
                <i class="fas fa-plus-circle"></i>
                <br>
                <span>{{Ajouter un enfant}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                <i class="fas fa-wrench"></i>
                <br>
                <span>{{Configuration}}</span>
            </div>
        </div>
        <legend><i class="fas fa-child"></i> {{Mes enfants}}</legend>
        <?php
        if (count($eqLogics) == 0) {
            echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement, cliquez sur "Ajouter un enfant" pour commencer}}</div>';
        } else {
            echo '<div class="input-group" style="margin:5px;">';
            echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
            echo '<div class="input-group-btn"><a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a></div>';
            echo '</div>';
            echo '<div class="eqLogicThumbnailContainer">';
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $plugin->getPathImgIcon() . '">';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
    </div>

    <!-- ===================== Page équipement ===================== -->
    <div class="col-xs-12 eqLogic" style="display: none;">
        <div class="input-group pull-right" style="display:inline-flex;">
            <span class="input-group-btn">
                <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
                <a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
                <a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
            </span>
        </div>
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Équipement}}</a></li>
            <li role="presentation"><a href="#behaviourtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-star-half-alt"></i> {{Comportements}}</a></li>
            <li role="presentation"><a href="#paliertab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-trophy"></i> {{Paliers}}</a></li>
            <li role="presentation"><a href="#historytab" aria-controls="home" role="tab" data-toggle="tab" id="bt_historyTab"><i class="fas fa-history"></i> {{Historique}}</a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
        </ul>
        <div class="tab-content">

            <!-- ---------- Équipement ---------- -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="col-lg-6">
                            <legend><i class="fas fa-wrench"></i> {{Général}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Prénom de l'enfant}}</label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Prénom}}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Objet parent}}</label>
                                <div class="col-sm-6">
                                    <select class="eqLogicAttr form-control" data-l1key="object_id">
                                        <option value="">{{Aucun}}</option>
                                        <?php
                                        $options = '';
                                        foreach ((jeeObject::buildTree(null, false)) as $object) {
                                            $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                        }
                                        echo $options;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                                <div class="col-sm-6">
                                    <?php
                                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                        echo '<label class="checkbox-inline">';
                                        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Options}}</label>
                                <div class="col-sm-6">
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
                                </div>
                            </div>

                            <legend><i class="fas fa-calendar-alt"></i> {{Garde alternée}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Semaines de garde}}</label>
                                <div class="col-sm-6">
                                    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="garde">
                                        <option value="paires">{{Semaines paires (ISO)}}</option>
                                        <option value="impaires">{{Semaines impaires (ISO)}}</option>
                                        <option value="toutes">{{Toutes les semaines}}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Ignorer les points hors garde}}
                                    <sup><i class="fas fa-question-circle tooltips" title="{{Hors semaine de garde, les points sont refusés sauf si la présence forcée est activée}}"></i></sup>
                                </label>
                                <div class="col-sm-6">
                                    <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="bloquerHorsGarde">
                                </div>
                            </div>
                            <div class="alert alert-info col-sm-10 col-sm-offset-1">
                                {{Le score de la semaine repart à 0 le lundi de chaque semaine de garde. L'ancien score est archivé (Score semaine précédente + onglet Historique) et le score total continue. La présence forcée (jours en plus hors garde) se désactive toute seule au changement de semaine.}}
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <legend><i class="fas fa-bell"></i> {{Notifications}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Commande de notification}}
                                    <sup><i class="fas fa-question-circle tooltips" title="{{Commande action de type message (Pushover, virtuel qui lance ton scénario Notify…)}}"></i></sup>
                                </label>
                                <div class="col-sm-6">
                                    <div class="input-group">
                                        <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="notifCmd" placeholder="#[Objet][Équipement][Commande]#">
                                        <span class="input-group-btn">
                                            <a class="btn btn-default roundedRight" id="bt_selectNotifCmd"><i class="fas fa-list-alt"></i></a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Prévenir quand un palier est atteint}}</label>
                                <div class="col-sm-6">
                                    <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="notifPalier">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Bilan automatique}}</label>
                                <div class="col-sm-6">
                                    <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="bilanEnable">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Jour du bilan (semaine de garde)}}</label>
                                <div class="col-sm-6">
                                    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="bilanDay">
                                        <option value="1">{{Lundi}}</option>
                                        <option value="2">{{Mardi}}</option>
                                        <option value="3">{{Mercredi}}</option>
                                        <option value="4">{{Jeudi}}</option>
                                        <option value="5">{{Vendredi}}</option>
                                        <option value="6">{{Samedi}}</option>
                                        <option value="7">{{Dimanche}}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Heure du bilan}}</label>
                                <div class="col-sm-6">
                                    <input type="time" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="bilanHour">
                                </div>
                            </div>

                            <legend><i class="fas fa-info-circle"></i> {{État actuel}}</legend>
                            <div id="div_bpState" class="col-sm-12"></div>
                        </div>
                    </fieldset>
                </form>
            </div>

            <!-- ---------- Comportements ---------- -->
            <div role="tabpanel" class="tab-pane" id="behaviourtab">
                <br>
                <div class="alert alert-info">{{Chaque comportement crée une commande action (bouton). Valeur positive = points gagnés, négative = points perdus. Sauvegarder pour créer/mettre à jour les commandes.}}</div>
                <a class="btn btn-success btn-sm pull-right" id="bt_addBehaviour"><i class="fas fa-plus-circle"></i> {{Ajouter un comportement}}</a>
                <a class="btn btn-default btn-sm pull-right" id="bt_addDefaultBehaviours" style="margin-right:5px;"><i class="fas fa-magic"></i> {{Ajouter les comportements par défaut}}</a>
                <br><br>
                <table class="table table-bordered table-condensed" id="table_behaviours">
                    <thead>
                        <tr>
                            <th>{{Comportement}}</th>
                            <th style="width:140px;">{{Points}}</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- ---------- Paliers ---------- -->
            <div role="tabpanel" class="tab-pane" id="paliertab">
                <br>
                <div class="alert alert-info">{{Les paliers s'appliquent au score de la semaine. Valeur positive = récompense (atteinte quand le score monte jusqu'à la valeur). Valeur négative = sanction (atteinte quand le score descend à la valeur ou en dessous). Chaque palier crée une commande binaire.}}</div>
                <a class="btn btn-success btn-sm pull-right" id="bt_addPalier"><i class="fas fa-plus-circle"></i> {{Ajouter un palier}}</a>
                <br><br>
                <table class="table table-bordered table-condensed" id="table_paliers">
                    <thead>
                        <tr>
                            <th>{{Récompense}}</th>
                            <th style="width:140px;">{{Points requis}}</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- ---------- Historique ---------- -->
            <div role="tabpanel" class="tab-pane" id="historytab">
                <br>
                <a class="btn btn-default btn-sm pull-right" id="bt_refreshHistory"><i class="fas fa-sync"></i> {{Rafraîchir}}</a>
                <legend><i class="fas fa-calendar-week"></i> {{Semaines passées}}</legend>
                <table class="table table-bordered table-condensed" id="table_weeks">
                    <thead><tr><th>{{Du}}</th><th>{{Au}}</th><th>{{Score}}</th><th>{{Palier}}</th><th>{{Sanction}}</th></tr></thead>
                    <tbody></tbody>
                </table>
                <legend><i class="fas fa-list-ul"></i> {{Événements (500 derniers)}}</legend>
                <table class="table table-bordered table-condensed" id="table_events">
                    <thead><tr><th style="width:150px;">{{Date}}</th><th>{{Comportement}}</th><th style="width:80px;">{{Points}}</th><th>{{Commentaire}}</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- ---------- Commandes ---------- -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <br>
                <div class="table-responsive">
                    <table id="table_cmd" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                                <th>{{Type}}</th>
                                <th style="min-width:260px;">{{Options}}</th>
                                <th>{{Valeur}}</th>
                                <th style="min-width:80px;width:200px;">{{Actions}}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_file('desktop', 'bonsPoints', 'js', 'bonsPoints'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
