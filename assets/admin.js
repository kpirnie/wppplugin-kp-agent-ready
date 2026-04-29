( function () {
    'use strict';

    /**
     * Add a new row by cloning the hidden <template> and replacing __INDEX__.
     */
    document.addEventListener( 'click', function ( e ) {
        var btn = e.target.closest( '.kp-ar-add-row' );
        if ( ! btn ) { return; }

        var repeaterId = btn.dataset.repeater;
        var repeater   = btn.closest( '.kp-ar-repeater' );
        var tpl        = repeater ? repeater.querySelector( 'template.kp-ar-row-tpl' ) : null;
        var rows       = repeater ? repeater.querySelector( '.kp-ar-repeater-rows' )   : null;
        if ( ! tpl || ! rows ) { return; }

        var count = rows.querySelectorAll( '.kp-ar-repeater-row' ).length;
        var html  = tpl.innerHTML.replace( /__INDEX__/g, String( count ) );
        var wrap  = document.createElement( 'div' );
        wrap.innerHTML = html;

        var newRow = wrap.firstElementChild;
        if ( newRow ) {
            newRow.dataset.rowIndex = count;
            rows.appendChild( newRow );
        }

        refreshRowNumbers( rows );
    } );

    /**
     * Remove the clicked row and reindex remaining rows.
     */
    document.addEventListener( 'click', function ( e ) {
        var btn = e.target.closest( '.kp-ar-remove-row' );
        if ( ! btn ) { return; }

        var row  = btn.closest( '.kp-ar-repeater-row' );
        var rows = row ? row.closest( '.kp-ar-repeater-rows' ) : null;
        if ( ! row || ! rows ) { return; }

        row.remove();
        reindexRows( rows );
        refreshRowNumbers( rows );
    } );

    /**
     * Update the visible ordinal number shown in each row header.
     *
     * @param {HTMLElement} rows The .kp-ar-repeater-rows container
     */
    function refreshRowNumbers( rows ) {
        rows.querySelectorAll( '.kp-ar-repeater-row' ).forEach( function ( row, i ) {
            var num = row.querySelector( '.kp-ar-row-num' );
            if ( num ) { num.textContent = String( i + 1 ); }
        } );
    }

    /**
     * Reindexes field name attributes after a row is removed so that
     * the submitted array has contiguous numeric keys.
     *
     * @param {HTMLElement} rows The .kp-ar-repeater-rows container
     */
    function reindexRows( rows ) {
        rows.querySelectorAll( '.kp-ar-repeater-row' ).forEach( function ( row, newIndex ) {
            var oldIndex = row.dataset.rowIndex;

            if ( oldIndex !== undefined && oldIndex !== String( newIndex ) ) {
                row.querySelectorAll( '[name]' ).forEach( function ( el ) {
                    el.name = el.name.replace(
                        /^([^\[]+\[[^\]]+\])\[\d+\]/,
                        '$1[' + newIndex + ']'
                    );
                } );
            }

            row.dataset.rowIndex = newIndex;
        } );
    }

} )();