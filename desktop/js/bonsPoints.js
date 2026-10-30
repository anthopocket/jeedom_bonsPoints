/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/* ======================= Comportements ======================= */

function bpNewId() {
  return 'b' + Date.now() + Math.floor(Math.random() * 1000)
}

function addBehaviourRow(_b) {
  _b = _b || {}
  var tr = $('<tr class="behaviour">' +
    '<td><input type="hidden" class="bAttr" data-key="id"><input class="form-control input-sm bAttr" data-key="name" placeholder="{{Ex. Va au lit à l\'heure}}"></td>' +
    '<td><input type="number" step="1" class="form-control input-sm bAttr" data-key="points"></td>' +
    '<td><a class="btn btn-danger btn-xs bt_removeRow"><i class="fas fa-trash"></i></a></td>' +
    '</tr>')
  tr.find('[data-key=id]').val(_b.id || bpNewId())
  tr.find('[data-key=name]').val(_b.name || '')
  tr.find('[data-key=points]').val((_b.points !== undefined) ? _b.points : 1)
  bpColorPoints(tr)
  $('#table_behaviours tbody').append(tr)
}

function addPalierRow(_p) {
  _p = _p || {}
  var tr = $('<tr class="palier">' +
    '<td><input type="hidden" class="pAttr" data-key="id"><input class="form-control input-sm pAttr" data-key="name" placeholder="{{Ex. Dessert au choix}}"></td>' +
    '<td><input type="number" step="1" class="form-control input-sm pAttr" data-key="points"></td>' +
    '<td><a class="btn btn-danger btn-xs bt_removeRow"><i class="fas fa-trash"></i></a></td>' +
    '</tr>')
  tr.find('[data-key=id]').val(_p.id || ('p' + Date.now() + Math.floor(Math.random() * 1000)))
  tr.find('[data-key=name]').val(_p.name || '')
  tr.find('[data-key=points]').val((_p.points !== undefined) ? _p.points : 10)
  $('#table_paliers tbody').append(tr)
}

function bpColorPoints(tr) {
  var input = tr.find('[data-key=points]')
  var v = parseInt(input.val(), 10) || 0
  input.css('color', v > 0 ? 'var(--al-success-color, #2e9e4f)' : (v < 0 ? 'var(--al-danger-color, #d9534f)' : ''))
}

$('#bt_addBehaviour').off('click').on('click', function () {
  addBehaviourRow()
  modifyWithoutSave = true
})
$('#bt_addDefaultBehaviours').off('click').on('click', function () {
  $.ajax({
    type: 'POST',
    url: 'plugins/bonsPoints/core/ajax/bonsPoints.ajax.php',
    data: { action: 'getDefaults' },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error)
    },
    success: function (data) {
      if (data.state != 'ok') {
        $.fn.showAlert({ message: data.result, level: 'danger' })
        return
      }
      var existing = []
      $('#table_behaviours tbody tr.behaviour [data-key=name]').each(function () {
        existing.push($(this).val().trim().toLowerCase())
      })
      var added = 0
      data.result.behaviours.forEach(function (b) {
        if (existing.indexOf(b.name.toLowerCase()) >= 0) return
        b.id = bpNewId() + added
        addBehaviourRow(b)
        added++
      })
      if (added > 0) modifyWithoutSave = true
      $.fn.showAlert({ message: added + ' {{comportement(s) ajouté(s), pensez à sauvegarder}}', level: 'success' })
    }
  })
})
$('#bt_addPalier').off('click').on('click', function () {
  addPalierRow()
  modifyWithoutSave = true
})
$('#table_behaviours, #table_paliers').off('click', '.bt_removeRow').on('click', '.bt_removeRow', function () {
  $(this).closest('tr').remove()
  modifyWithoutSave = true
})
$('#table_behaviours').off('input', '[data-key=points]').on('input', '[data-key=points]', function () {
  bpColorPoints($(this).closest('tr'))
})
$('#table_behaviours tbody').sortable({ axis: 'y', cursor: 'move', items: '.behaviour', placeholder: 'ui-state-highlight', tolerance: 'intersect', forcePlaceholderSize: true })

/* ======================= Notification ======================= */

$('#bt_selectNotifCmd').off('click').on('click', function () {
  jeedom.cmd.getSelectModal({ cmd: { type: 'action', subType: 'message' } }, function (result) {
    $('.eqLogicAttr[data-l2key=notifCmd]').val(result.human)
  })
})

/* ======================= Historique ======================= */

function bpEsc(s) {
  return $('<div>').text(s === undefined || s === null ? '' : String(s)).html()
}

function bpPts(p) {
  p = parseInt(p, 10) || 0
  var color = p > 0 ? 'success' : (p < 0 ? 'danger' : 'default')
  return '<span class="label label-' + color + '">' + (p > 0 ? '+' : '') + p + '</span>'
}

function loadHistory() {
  var id = $('.eqLogicAttr[data-l1key=id]').val()
  if (!id) return
  $.ajax({
    type: 'POST',
    url: 'plugins/bonsPoints/core/ajax/bonsPoints.ajax.php',
    data: { action: 'getHistory', id: id },
    dataType: 'json',
    global: false,
    error: function (request, status, error) {
      handleAjaxError(request, status, error)
    },
    success: function (data) {
      if (data.state != 'ok') {
        $.fn.showAlert({ message: data.result, level: 'danger' })
        return
      }
      var r = data.result
      $('#div_bpState').html(
        '<table class="table table-condensed">' +
        '<tr><td>{{Suivi}}</td><td>' + (r.actif == 1 ? '<span class="label label-success">{{Actif}}</span>' : '<span class="label label-default">{{En pause (hors garde)}}</span>') +
        (r.presence == 1 ? ' <span class="label label-warning">{{Présence forcée}}</span>' : '') + '</td></tr>' +
        '<tr><td>{{Période depuis le}}</td><td>' + bpEsc(r.period) + '</td></tr>' +
        '<tr><td>{{Score semaine}}</td><td><b>' + bpEsc(r.score) + ' pts</b></td></tr>' +
        '<tr><td>{{Palier atteint}}</td><td>' + (r.palier ? bpEsc(r.palier) : '{{Aucun}}') + '</td></tr>' +
        (r.sanction ? '<tr><td>{{Sanction atteinte}}</td><td><span class="label label-danger">' + bpEsc(r.sanction) + '</span></td></tr>' : '') +
        '<tr><td>{{Prochain palier}}</td><td>' + (r.next ? bpEsc(r.next) : '{{Tous atteints}}') + '</td></tr>' +
        '<tr><td>{{Semaine précédente}}</td><td>' + bpEsc(r.prevScore) + ' pts</td></tr>' +
        '<tr><td>{{Total}}</td><td>' + bpEsc(r.total) + ' pts</td></tr>' +
        '</table>')

      var wb = $('#table_weeks tbody').empty()
      if (r.weeks.length == 0) wb.append('<tr><td colspan="5" class="text-center">{{Aucune semaine archivée pour le moment}}</td></tr>')
      r.weeks.forEach(function (w) {
        wb.append('<tr><td>' + bpEsc(w.start) + '</td><td>' + bpEsc(w.end) + '</td><td>' + bpPts(w.score) + '</td><td>' + bpEsc(w.palier || '-') + '</td><td>' + bpEsc(w.sanction || '-') + '</td></tr>')
      })

      var eb = $('#table_events tbody').empty()
      if (r.events.length == 0) eb.append('<tr><td colspan="4" class="text-center">{{Aucun événement}}</td></tr>')
      r.events.forEach(function (e) {
        var cur = (e.period == r.period) ? ' style="font-weight:bold;"' : ''
        eb.append('<tr' + cur + '><td>' + bpEsc(e.date) + '</td><td>' + bpEsc(e.label) + '</td><td>' + bpPts(e.points) + '</td><td>' + bpEsc(e.comment) + '</td></tr>')
      })
    }
  })
}

$('#bt_refreshHistory, #bt_historyTab').off('click.bp').on('click.bp', function () {
  loadHistory()
})

/* ======================= Hooks plugin.template ======================= */

function printEqLogic(_eqLogic) {
  $('#table_behaviours tbody').empty()
  $('#table_paliers tbody').empty()
  var conf = _eqLogic.configuration || {}
  if (Array.isArray(conf.behaviours)) conf.behaviours.forEach(addBehaviourRow)
  if (Array.isArray(conf.paliers)) conf.paliers.forEach(addPalierRow)
  $('#div_bpState').empty()
  $('#table_weeks tbody, #table_events tbody').empty()
  if (_eqLogic.id) loadHistory()
}

function saveEqLogic(_eqLogic) {
  if (!_eqLogic.configuration) _eqLogic.configuration = {}
  var behaviours = []
  $('#table_behaviours tbody tr.behaviour').each(function () {
    var name = $(this).find('[data-key=name]').val().trim()
    if (name == '') return
    behaviours.push({
      id: $(this).find('[data-key=id]').val(),
      name: name,
      points: parseInt($(this).find('[data-key=points]').val(), 10) || 0
    })
  })
  var paliers = []
  $('#table_paliers tbody tr.palier').each(function () {
    var name = $(this).find('[data-key=name]').val().trim()
    if (name == '') return
    paliers.push({ id: $(this).find('[data-key=id]').val(), name: name, points: parseInt($(this).find('[data-key=points]').val(), 10) || 0 })
  })
  _eqLogic.configuration.behaviours = behaviours
  _eqLogic.configuration.paliers = paliers
  return _eqLogic
}

/* ======================= Commandes ======================= */

$('#table_cmd').sortable({ axis: 'y', cursor: 'move', items: '.cmd', placeholder: 'ui-state-highlight', tolerance: 'intersect', forcePlaceholderSize: true })

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} }
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span></td>'
  tr += '<td>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
  tr += '<div style="margin-top:7px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '</div>'
  tr += '</td>'
  tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>'
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  tr += '</td>'
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var tr = $('#table_cmd tbody tr').last()
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $.fn.showAlert({ message: error.message, level: 'danger' })
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result)
      tr.setValues(_cmd, '.cmdAttr')
      jeedom.cmd.changeType(tr, init(_cmd.subType))
    }
  })
}
