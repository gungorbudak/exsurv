'use strict';


var baseUrl = 'https://exsurv.soic.iupui.edu/';
// global spinner and target, actually body element
var target = document.body;
var spinner = new Spinner({
  top: '25%',
  lines: 12,
  length: 12,
  width: 8,
  radius: 16,
});

function getQuery() {
    return $('input[name="query"]').val();
}

function setQuery(query) {
    $('input[name="query"]').val(query);
}

function getPVal() {
    return $('input[name="pval"]').val();
}

function setPVal(pVal) {
    $('input[name="pval"]').val(pVal);
}

function getCancer() {
    return $('select[name="cancer"]').val();
}

function setCancer(cancer) {
    $('select[name="cancer"]').val(cancer);
}

// function getSurvival() {
//     return $('select[name="survival"]').val();
// }
//
// function setSurvival(survival) {
//     $('select[name="survival"]').val(survival);
// }

function getClassification() {
    return $('select[name="classification"]').val();
}

function setClassification(classification) {
    $('select[name="classification"]').val(classification);
}

function getHashValue(key) {
    var matches = window.location.hash.match(new RegExp(key + '=([^&]*)'));
    return matches ? matches[1]: null;
}

/*
Given any message, alerts the user by adding
an alert box to warnings div in index.html
*/
var alertUser = function(message) {
    var $closeBtn = $('<button>')
        .attr('type', 'button')
        .attr('data-dismiss', 'alert')
        .addClass('close')
        .append($('<span>').append('&times;'));
    var $message = $('<div>')
        .html(message)
        .addClass('alert alert-warning alert-dismissible')
        .attr('role', 'alert')
        .append($closeBtn);
    $('#div-warnings').append($message);
};

var generateTable = function(data, query, pVal, cancer, t) {
    // generate thead, tr for the table header
    var $thead = $('<thead>');
    var $trHeader = $('<tr>');
    // var isHazardRatio = data[0]['info'].hasOwnProperty('hazard_ratio');

    $trHeader.append($('<th>').addClass('text-center').html('#'));
    $trHeader.append($('<th>').addClass('text-center').html('Exon ID'));
    $trHeader.append($('<th>').addClass('text-center').html('Transcript ID'));
    $trHeader.append($('<th>').addClass('text-center').html('Gene ID'));
    $trHeader.append($('<th>').addClass('text-center').html('Gene Symbol'));
    // $trHeader.append($('<th>').addClass('text-center').html('LR p-value'));
    $trHeader.append($('<th>').addClass('text-center').html('Hazard ratio'));
    $trHeader.append($('<th>').addClass('text-center').html('p-value'));
    $trHeader.append($('<th>').addClass('text-center').html('q-value'));
    // if (isHazardRatio) {
    //   $trHeader.append($('<th>').html('Hazard ratio'));
    // }
    // $trHeader.append($('<th>').html('p-value'));
    $trHeader.append($('<th>').addClass('text-center').html('Plot'));
    $trHeader.append($('<th>').addClass('text-center').html('Export'));
    $thead.append($trHeader);
    // generate tbody, trs for the data
    var $tbody = $('<tbody>');
    data.forEach(function(d, i) {
        var $tr = $('<tr>');
        $tr.append($('<td>').addClass('text-center').html(i + 1));
        $tr.append(
            $('<td>').addClass('text-center').append(
                $('<a>')
                .attr('href', 'https://www.ensembl.org/id/' + d['info']['exon_id'])
                .attr('title', 'View on Ensembl')
                .attr('target', '_blank')
                .html(d['info']['exon_id'])
            ));
        $tr.append(
            $('<td>').addClass('text-center').append(
                $('<a>')
                .attr('href', 'https://www.ensembl.org/id/' + d['info']['transcript_id'])
                .attr('title', 'View on Ensembl')
                .attr('target', '_blank')
                .html(d['info']['transcript_id'])
            ));
        $tr.append(
            $('<td>').addClass('text-center').append(
                $('<a>')
                .attr('href', 'https://www.ensembl.org/id/' + d['info']['gene_id'])
                .attr('title', 'View on Ensembl')
                .attr('target', '_blank')
                .html(d['info']['gene_id'])
            ));
        $tr.append(
            $('<td>').addClass('text-center').append(
                $('<a>')
                .attr('href', 'https://www.genecards.org/cgi-bin/carddisp.pl?gene=' + d['info']['gene_symbol'])
                .attr('title', 'View on GeneCards')
                .attr('target', '_blank')
                .html(d['info']['gene_symbol'])
            ));
        // $tr.append(
        //     $('<td>').addClass('text-center')
        //     .html(parseFloat(d['info']['log_rank_pval']).toFixed(3))
        // );
        $tr.append(
            $('<td>').addClass('text-center')
            .html(parseFloat(d['info']['hazard_ratio']).toFixed(3))
        );
        $tr.append(
            $('<td>').addClass('text-center')
            .html(parseFloat(d['info']['hazard_ratio_pval']).toFixed(3))
        );
        $tr.append(
            $('<td>').addClass('text-center')
            .html(parseFloat(d['info']['hazard_ratio_qval']).toFixed(3))
        );
        // if (isHazardRatio) {
        //   $tr.append(
        //       $('<td>')
        //       .html(parseFloat(d['info']['hazard_ratio']).toFixed(3))
        //   );
        // }
        // $tr.append(
        //     $('<td>')
        //     .html(parseFloat(d['info']['pval']).toFixed(3))
        // );
        $tr.append(
            $('<td>').addClass('text-center')
            .append(
                $('<a>')
                .addClass('zoom')
                .attr('href', d['plot'])
                .attr('data-featherlight', 'image')
                .html(
                    $('<img>')
                    .addClass('image-plot')
                    .attr('src', d['plot'])
                )
            ));
        $tr.append(
            $('<td>').addClass('text-center')
            .append(
                $('<button>')
                .addClass('btn btn-default btn-xs button-export')
                .attr('data-exon-id', d['info']['exon_id'])
                .attr('data-cancer', d['info']['cancer'])
                .attr('data-classification', d['info']['classification'])
                .attr('data-format', 'svg')
                .attr('title', 'Export plot as SVG')
                .append(
                    $('<i>')
                    .addClass('fa fa-file-image-o fa-fw')
                ))
            .append(
                $('<button>')
                .addClass('btn btn-default btn-xs button-export')
                .attr('data-exon-id', d['info']['exon_id'])
                .attr('data-cancer', d['info']['cancer'])
                .attr('data-classification', d['info']['classification'])
                .attr('data-format', 'tsv')
                .attr('title', 'Export expression data as TSV')
                .append(
                    $('<i>')
                    .addClass('fa fa-file-excel-o fa-fw')
                ))
            );
        $tbody.append($tr);
    });
    // results heading
    var $heading = $('<div>')
        .append(
            $('<h1>')
            .html('Search results')
        )
        .append($('<hr>'))
        .append(
            $('<p>')
                .html([
                    'Obtained ', data.length, ' result(s) for ', query,
                    ' (p-value &lt; ', pVal, ', in ', cancer + ') in ',
                    t, ' seconds.'
                ].join(''))
        );

    // results table
    var $table = $('<table>')
      .addClass('table table-condensed table-results')
      .append($thead)
      .append($tbody);

    $table.DataTable({
      "order": [[ 6, "asc" ]]
    });

    var $div = $('<div>')
      .addClass('table-responsive')
      .css('margin-top', '20px')
      .append($table);
    var $final = $('<div>').append($heading).append($div);
    return $final;
};

var _clear = function() {
    var $description = $('#div-description');
    var $warnings = $('#div-warnings');
    var $container = $('#div-results');
    $description.hide();
    $warnings.html('');
    $container.html('');
};

// var _main = function(query, pVal, cancer, survival, classification) {
var _main = function(query, pVal, cancer, classification) {

    var request = [
      baseUrl, 'app/app.php',
      '?query=', query,
      '&pval=', pVal,
      '&cancer=', cancer,
      // '&survival=', survival,
      '&classification=', classification,
      '&format=', 'png'
    ].join('');
    var $container = $('#div-results');
    var t0 = new Date().getTime();
    var t1;
    var t;

    console.log(request);
    // start loader here
    spinner.spin(target);

    $.get(request, function(data) {
        _clear($container);
        t1 = new Date().getTime();
        t = (t1 - t0)/1000;

        // stop loader here
        spinner.stop();

        // make object array here
        var dataArray = $.map(data, function(value, index) {
            return [value];
        });

        // generate table if there is any data
        if (dataArray.length > 0) {
            // sort the array by log-rank pvalue
            var sortedDataArray = dataArray.sort(function(a, b) {
              return (a['info']['log_rank_pval'] > b['info']['log_rank_pval'])
                        ? 1 : ((b['info']['log_rank_pval'] > a['info']['log_rank_pval'])
                          ? -1 : 0);
            });
            var $table = generateTable(dataArray, query, pVal, cancer, t);
            $container.append($table);
        } else {
            alertUser('There is no survival analysis data matching your query.');
        }
    });
};

var onSearch = function() {
    var query = getQuery();
    var pVal = getPVal();
    var cancer = getCancer();
    // var survival = getSurvival();
    var classification = getClassification();

    var parameters = [
      '#query=', query,
      '&pval=', pVal,
      '&cancer=', cancer,
      // '&survival=', survival,
      '&classification=', classification
    ].join('');

    if (window.location.pathname != '/' &&
        window.location.pathname != '/index.html') {
        window.location.href = baseUrl + parameters;
    } else {
        window.location.hash = parameters;
    }

    // _main(query, pVal, cancer, survival, classification);
    _main(query, pVal, cancer, classification);
};

var onExport = function($btn) {
  var exonId = $btn.data('exon-id');
  var cancer = $btn.data('cancer');
  // var survival = $btn.data('survival');
  var classification = $btn.data('classification');
  var format = $btn.data('format');

  var request = [
    baseUrl, 'app/app.php',
    '?query=', exonId,
    '&cancer=', cancer,
    // '&survival=', survival,
    '&classification=', classification,
    '&format=', format
  ].join('');

  console.log(request);
  // start loader here
  spinner.spin(target);

  $.get(request, function(data) {

    // stop loader here
    spinner.stop();

    // download data if there is any
    if (data != null) {
      var source = (format == 'svg') ? data[exonId]['plot']: data;
      var type = (format == 'svg') ? 'image/svg+xml': 'text/tsv';
      var name = exonId + '_' + cancer + '.' + format;
      var blob = new Blob([source], {type: type});
      // using FileSaver.js
      saveAs(blob, name);
    } else {
      alertUser('There is no data to export.');
    }

  });
};

$(document).ready(function() {

  // if query is given search for results
  var query = getHashValue('query');
  var pVal = getHashValue('pval');
  var cancer = getHashValue('cancer');
  // var survival = getHashValue('survival');
  var classification = getHashValue('classification');

  if (query !== null && pVal !== null && cancer !== null) {
    setQuery(query);
    setPVal(pVal);
    setCancer(cancer);
    // setSurvival(survival);
    setClassification(classification);

    // _main(query, pVal, cancer, survival, classification);
    _main(query, pVal, cancer, classification);
  }

  $('.button-search').on('click', function(e) {
    e.preventDefault();
    onSearch();
  });

  $('.form-search').on('submit', function(e) {
    e.preventDefault();
    onSearch();
  });

  $('#div-results').on('click', '.button-export', function(e) {
    e.preventDefault();
    onExport($(this));
  });
});
