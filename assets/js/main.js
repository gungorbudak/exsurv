'use strict';

var baseUrl = 'https://exsurv.soic.iupui.edu/';
// global spinner and target, actually body element
var target = document.body;
var spinner = new Spinner();

var getQuery = function() {
    return $('input[name="query"]').val();
};

var setQuery = function(query) {
    $('input[name="query"]').val(query);
};

var getPVal = function() {
    return $('input[name="pval"]').val();
};

var setPVal = function(pVal) {
    $('input[name="pval"]').val(pVal);
};

var getCancer = function() {
    return $('select[name="cancer"]').val();
};

var setCancer = function(cancer) {
    $('select[name="cancer"]').val(cancer);
};

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
    $trHeader.append($('<th>').addClass('text-center').html('#'));
    $trHeader.append($('<th>').addClass('text-center').html('Exon ID'));
    $trHeader.append($('<th>').addClass('text-center').html('Transcript ID'));
    $trHeader.append($('<th>').addClass('text-center').html('Gene ID'));
    $trHeader.append($('<th>').addClass('text-center').html('Gene Symbol'));
    $trHeader.append($('<th>').addClass('text-center').html('p-value'));
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
                .attr('href', 'http://www.ensembl.org/id/' + d['info']['exon_id'])
                .attr('title', 'View on Ensembl')
                .attr('target', '_blank')
                .html(d['info']['exon_id'])
            ));
        $tr.append(
            $('<td>').addClass('text-center').append(
                $('<a>')
                .attr('href', 'http://www.ensembl.org/id/' + d['info']['transcript_id'])
                .attr('title', 'View on Ensembl')
                .attr('target', '_blank')
                .html(d['info']['transcript_id'])
            ));
        $tr.append(
            $('<td>').addClass('text-center').append(
                $('<a>')
                .attr('href', 'http://www.ensembl.org/id/' + d['info']['gene_id'])
                .attr('title', 'View on Ensembl')
                .attr('target', '_blank')
                .html(d['info']['gene_id'])
            ));
        $tr.append(
            $('<td>').addClass('text-center').append(
                $('<a>')
                .attr('href', 'http://www.genecards.org/cgi-bin/carddisp.pl?gene=' + d['info']['gene_symbol'])
                .attr('title', 'View on GeneCards')
                .attr('target', '_blank')
                .html(d['info']['gene_symbol'])
            ));
        $tr.append(
            $('<td>')
            .addClass('text-center')
            .html(parseFloat(d['info']['pval']).toFixed(3))
        );
        $tr.append(
            $('<td>')
            .addClass('text-center')
            .append(
                $('<a>')
                .addClass('zoom-in')
                .attr('href', d['plot'])
                .attr('data-featherlight', 'image')
                .html(
                    $('<img>')
                    .addClass('img-thumb')
                    .attr('src', d['plot'])
                )
            ));
        $tr.append(
            $('<td>')
            .addClass('text-center')
            .append(
                $('<button>')
                .addClass('btn btn-default btn-xs')
                .attr('id', 'btn-export')
                .attr('data-exon-id', d['info']['exon_id'])
                .attr('data-cancer', d['info']['cancer'])
                .attr('data-format', 'svg')
                .attr('title', 'Export plot as SVG')
                .append(
                    $('<i>')
                    .addClass('fa fa-file-image-o')
                ))
            .append('&nbsp;')
            .append(
                $('<button>')
                .addClass('btn btn-default btn-xs')
                .attr('id', 'btn-export')
                .attr('data-exon-id', d['info']['exon_id'])
                .attr('data-cancer', d['info']['cancer'])
                .attr('data-format', 'tsv')
                .attr('title', 'Export expression data as TSV')
                .append(
                    $('<i>')
                    .addClass('fa fa-file-excel-o')
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
                .addClass('text-muted')
                .html([
                    'Obtained ', data.length, ' result(s) for ', query,
                    ' (p-value &le; ', pVal, ', in ', cancer + ') in ',
                    t, ' seconds.'
                ].join(''))
        );
    // results table
    var $table = $('<div>')
        .addClass('table-responsive')
        .css('margin-top', '20px')
        .append(
            $('<table>')
                .addClass('table table-results')
                .append($thead)
                .append($tbody)
        );
    var $final = $('<div>').append($heading).append($table);
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

var _main = function(query, pVal, cancer) {

    var request = [
            baseUrl, 'app/app.php',
            '?query=', query,
            '&pval=', pVal,
            '&cancer=', cancer,
            '&format=', 'png'
            ].join(''),
        $container = $('#div-results'),
        t0 = new Date().getTime(),
        t1, t;

    console.log(request);
    spinner.spin(target);
    $.get(request, function(data) {
        _clear($container);
        t1 = new Date().getTime();
        t = (t1 - t0)/1000;
        spinner.stop();
        // make object array here
        var arrData = $.map(data, function(value, index) {
            return [value];
        });
        // generate table if there is any data
        if (arrData.length > 0) {
            var $table = generateTable(arrData, query, pVal, cancer, t);
            $container.append($table);
        } else {
            alertUser('There is no survival analysis data matching your query.');
        }
    });
};

var onSearch = function(argument) {
    var query = getQuery(),
        pVal = getPVal(),
        cancer = getCancer(),
        parameters = [
            '#query=', query,
            '&pval=', pVal,
            '&cancer=', cancer
        ].join('');
    if (window.location.pathname != '/' &&
        window.location.pathname != '/index.html') {
        window.location.href = baseUrl + parameters;
    } else {
        window.location.hash = parameters;
    }
    _main(query, pVal, cancer);
};

var onExport = function($btn) {
    var exonId = $btn.data('exon-id'),
        cancer = $btn.data('cancer'),
        format = $btn.data('format');

    var request = [
        baseUrl, 'app/app.php',
        '?query=', exonId,
        '&cancer=', cancer,
        '&format=', format
    ].join('');

    console.log(request);
    spinner.spin(target);
    $.get(request, function(data) {
        spinner.stop();
        // download data if there is any
        if (data != null) {
            var source = (format == 'svg') ? data[exonId]['plot']: data,
                type = (format == 'svg') ? 'image/svg+xml': 'text/tsv',
                name = exonId + '_' + cancer + '.' + format;
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
    var query = getHashValue('query'),
        pVal = getHashValue('pval'),
        cancer = getHashValue('cancer');

    if (query !== null && pVal !== null && cancer !== null) {
        setQuery(query);
        setPVal(pVal);
        setCancer(cancer);
        _main(query, pVal, cancer);
    }

    $('button[type="submit"]').on('click', function(e) {
        e.preventDefault();
        onSearch();
    });

    $('form.navbar-form').on('submit', function(e) {
        e.preventDefault();
        onSearch();
    });

    $('#div-results').on('click', 'button#btn-export', function(e) {
        e.preventDefault();
        onExport($(this));
    });
});
