/**
 * Interactive admin calendar: AJAX month navigation, event popover,
 * click-to-create, and drag-and-drop rescheduling.
 *
 * Progressive enhancement — without JS the prev/next links still page-reload
 * and each event chip is a normal edit link.
 */
( function () {
	'use strict';

	var cfg  = window.vevCalendar || {};
	var i18n = cfg.i18n || {};
	var app  = document.getElementById( 'vev-cal-app' );
	if ( ! app || ! cfg.nonce ) {
		return;
	}

	var ajaxUrl   = window.ajaxurl || '/wp-admin/admin-ajax.php';
	var popover   = null;
	var didDrag   = false;

	function t( key ) {
		return i18n[ key ] || key;
	}

	function currentMonth() {
		return app.getAttribute( 'data-month' ) || '';
	}

	function request( action, extra, onSuccess, onError ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', cfg.nonce );
		Object.keys( extra || {} ).forEach( function ( k ) {
			body.set( k, extra[ k ] );
		} );

		app.classList.add( 'vev-cal-loading' );

		fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				app.classList.remove( 'vev-cal-loading' );
				if ( res && res.success ) {
					onSuccess( res.data || {} );
				} else if ( onError ) {
					onError( res && res.data ? res.data.message : '' );
				}
			} )
			.catch( function () {
				app.classList.remove( 'vev-cal-loading' );
				if ( onError ) {
					onError( '' );
				}
			} );
	}

	function swapMonth( data ) {
		if ( typeof data.html === 'string' ) {
			app.innerHTML = data.html;
		}
		if ( data.month ) {
			app.setAttribute( 'data-month', data.month );
		}
	}

	/* ---------- Month navigation ---------- */

	function loadMonth( month, push ) {
		request( 'vev_cal_month', { month: month }, function ( data ) {
			swapMonth( data );
			if ( push && data.month ) {
				var url = new URL( window.location.href );
				url.searchParams.set( 'vev_cal_month', data.month );
				window.history.pushState( { vevMonth: data.month }, '', url.toString() );
			}
		}, function () {
			// Hard fallback: let the anchor navigate normally.
			var link = app.querySelector( '.vev-cal-nav[data-month="' + month + '"]' );
			window.location.href = link ? link.href : window.location.href;
		} );
	}

	window.addEventListener( 'popstate', function ( e ) {
		var month = ( e.state && e.state.vevMonth ) || new URLSearchParams( window.location.search ).get( 'vev_cal_month' );
		if ( month ) {
			request( 'vev_cal_month', { month: month }, swapMonth );
		}
	} );

	/* ---------- Popover ---------- */

	function closePopover() {
		if ( popover ) {
			popover.parentNode.removeChild( popover );
			popover = null;
		}
	}

	function buildRow( label, value ) {
		if ( ! value ) {
			return null;
		}
		var row = document.createElement( 'div' );
		row.className = 'vev-pop-row';
		var l = document.createElement( 'span' );
		l.className = 'vev-pop-label';
		l.textContent = label;
		var v = document.createElement( 'span' );
		v.className = 'vev-pop-value';
		v.textContent = value;
		row.appendChild( l );
		row.appendChild( v );
		return row;
	}

	function openPopover( anchor, ev ) {
		closePopover();

		popover = document.createElement( 'div' );
		popover.className = 'vev-cal-popover';
		popover.setAttribute( 'role', 'dialog' );

		var head = document.createElement( 'div' );
		head.className = 'vev-pop-head';
		var title = document.createElement( 'strong' );
		title.className = 'vev-pop-title';
		title.textContent = ev.title;
		head.appendChild( title );
		popover.appendChild( head );

		var when = ( ev.date || '' ) + ( ev.time ? ' · ' + ev.time : '' );
		[ buildRow( t( 'when' ), when ),
		  buildRow( t( 'where' ), ev.location ),
		  buildRow( t( 'category' ), ev.category ) ].forEach( function ( row ) {
			if ( row ) { popover.appendChild( row ); }
		} );

		if ( ev.statusLabel ) {
			var badge = document.createElement( 'span' );
			badge.className = 'vev-pop-badge';
			badge.textContent = ev.statusLabel;
			if ( ev.statusColor ) {
				badge.style.background = ev.statusColor;
			}
			popover.appendChild( badge );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'vev-pop-actions';
		if ( ev.editUrl ) {
			var edit = document.createElement( 'a' );
			edit.className = 'button button-primary';
			edit.href = ev.editUrl;
			edit.textContent = t( 'edit' );
			actions.appendChild( edit );
		}
		if ( ev.viewUrl && ev.postStatus === 'publish' ) {
			var view = document.createElement( 'a' );
			view.className = 'button';
			view.href = ev.viewUrl;
			view.target = '_blank';
			view.rel = 'noopener';
			view.textContent = t( 'view' );
			actions.appendChild( view );
		}
		popover.appendChild( actions );

		document.body.appendChild( popover );
		positionPopover( anchor );
	}

	function positionPopover( anchor ) {
		var rect = anchor.getBoundingClientRect();
		var pw   = popover.offsetWidth;
		var ph   = popover.offsetHeight;
		var top  = window.scrollY + rect.bottom + 6;
		var left = window.scrollX + rect.left;

		if ( left + pw > window.scrollX + document.documentElement.clientWidth - 8 ) {
			left = window.scrollX + document.documentElement.clientWidth - pw - 8;
		}
		if ( rect.bottom + ph > document.documentElement.clientHeight && rect.top - ph > 0 ) {
			top = window.scrollY + rect.top - ph - 6;
		}
		popover.style.top  = Math.max( 0, top ) + 'px';
		popover.style.left = Math.max( 0, left ) + 'px';
	}

	/* ---------- Quick create ---------- */

	function openCreate( cell ) {
		closePopover();
		var date = cell.getAttribute( 'data-date' );
		if ( ! date ) {
			return;
		}

		popover = document.createElement( 'div' );
		popover.className = 'vev-cal-popover vev-cal-create';
		popover.setAttribute( 'role', 'dialog' );

		var head = document.createElement( 'div' );
		head.className = 'vev-pop-head';
		head.textContent = t( 'newEvent' ) + ' · ' + date;
		popover.appendChild( head );

		var titleInput = inputField( 'text', t( 'titleLabel' ), t( 'titlePlace' ) );
		popover.appendChild( titleInput.wrap );

		var startInput = inputField( 'time', t( 'startLabel' ), '' );
		var endInput   = inputField( 'time', t( 'endLabel' ), '' );
		var times = document.createElement( 'div' );
		times.className = 'vev-pop-times';
		times.appendChild( startInput.wrap );
		times.appendChild( endInput.wrap );
		popover.appendChild( times );

		var allDayWrap = document.createElement( 'label' );
		allDayWrap.className = 'vev-pop-allday';
		var allDay = document.createElement( 'input' );
		allDay.type = 'checkbox';
		allDayWrap.appendChild( allDay );
		allDayWrap.appendChild( document.createTextNode( ' ' + t( 'allDayLabel' ) ) );
		popover.appendChild( allDayWrap );

		allDay.addEventListener( 'change', function () {
			startInput.field.disabled = allDay.checked;
			endInput.field.disabled   = allDay.checked;
		} );

		var msg = document.createElement( 'div' );
		msg.className = 'vev-pop-msg';
		popover.appendChild( msg );

		var actions = document.createElement( 'div' );
		actions.className = 'vev-pop-actions';

		var draftBtn = document.createElement( 'button' );
		draftBtn.type = 'button';
		draftBtn.className = 'button';
		draftBtn.textContent = t( 'createDraft' );
		actions.appendChild( draftBtn );

		var pubBtn = null;
		if ( cfg.canPublish ) {
			pubBtn = document.createElement( 'button' );
			pubBtn.type = 'button';
			pubBtn.className = 'button button-primary';
			pubBtn.textContent = t( 'publish' );
			actions.appendChild( pubBtn );
		}

		var cancelBtn = document.createElement( 'button' );
		cancelBtn.type = 'button';
		cancelBtn.className = 'button-link vev-pop-cancel';
		cancelBtn.textContent = t( 'cancel' );
		actions.appendChild( cancelBtn );
		cancelBtn.addEventListener( 'click', closePopover );

		popover.appendChild( actions );

		function submit( status, btn ) {
			var title = titleInput.field.value.trim();
			if ( ! title ) {
				msg.textContent = t( 'saveError' );
				titleInput.field.focus();
				return;
			}
			btn.disabled = true;
			if ( pubBtn ) { pubBtn.disabled = true; }
			draftBtn.disabled = true;

			request( 'vev_cal_quick_create', {
				month: currentMonth(),
				date: date,
				title: title,
				start_time: allDay.checked ? '' : startInput.field.value,
				end_time: allDay.checked ? '' : endInput.field.value,
				all_day: allDay.checked ? 1 : 0,
				post_status: status
			}, function ( data ) {
				closePopover();
				swapMonth( data );
			}, function ( message ) {
				btn.disabled = false;
				if ( pubBtn ) { pubBtn.disabled = false; }
				draftBtn.disabled = false;
				msg.textContent = message || t( 'saveError' );
			} );
		}

		draftBtn.addEventListener( 'click', function () { submit( 'draft', draftBtn ); } );
		if ( pubBtn ) {
			pubBtn.addEventListener( 'click', function () { submit( 'publish', pubBtn ); } );
		}

		document.body.appendChild( popover );
		positionPopover( cell );
		titleInput.field.focus();
	}

	function inputField( type, label, placeholder ) {
		var wrap = document.createElement( 'label' );
		wrap.className = 'vev-pop-field';
		var span = document.createElement( 'span' );
		span.textContent = label;
		var field = document.createElement( 'input' );
		field.type = type;
		if ( placeholder ) { field.placeholder = placeholder; }
		wrap.appendChild( span );
		wrap.appendChild( field );
		return { wrap: wrap, field: field };
	}

	/* ---------- Drag and drop ---------- */

	function onDragStart( e ) {
		var chip = e.target.closest( '.vev-cal-event' );
		if ( ! chip ) {
			return;
		}
		didDrag = true;
		e.dataTransfer.setData( 'text/plain', chip.getAttribute( 'data-id' ) );
		e.dataTransfer.effectAllowed = 'move';
		chip.classList.add( 'vev-cal-event--dragging' );
	}

	function onDragEnd( e ) {
		var chip = e.target.closest( '.vev-cal-event' );
		if ( chip ) {
			chip.classList.remove( 'vev-cal-event--dragging' );
		}
		setTimeout( function () { didDrag = false; }, 50 );
	}

	function dropTarget( e ) {
		var cell = e.target.closest( '.vev-cal-day' );
		if ( ! cell || cell.classList.contains( 'vev-cal-day--empty' ) || ! cell.getAttribute( 'data-date' ) ) {
			return null;
		}
		return cell;
	}

	function onDragOver( e ) {
		var cell = dropTarget( e );
		if ( ! cell ) {
			return;
		}
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		cell.classList.add( 'vev-cal-day--dropover' );
	}

	function onDragLeave( e ) {
		var cell = e.target.closest( '.vev-cal-day' );
		if ( cell ) {
			cell.classList.remove( 'vev-cal-day--dropover' );
		}
	}

	function onDrop( e ) {
		var cell = dropTarget( e );
		if ( ! cell ) {
			return;
		}
		e.preventDefault();
		cell.classList.remove( 'vev-cal-day--dropover' );
		var id = e.dataTransfer.getData( 'text/plain' );
		if ( ! id ) {
			return;
		}
		request( 'vev_cal_move_event', {
			month: currentMonth(),
			post_id: id,
			date: cell.getAttribute( 'data-date' )
		}, swapMonth, function ( message ) {
			// Reload the current month to restore truth, then surface the error.
			request( 'vev_cal_month', { month: currentMonth() }, swapMonth );
			window.alert( message || t( 'moveError' ) );
		} );
	}

	/* ---------- Event delegation ---------- */

	app.addEventListener( 'click', function ( e ) {
		var nav = e.target.closest( '.vev-cal-nav' );
		if ( nav ) {
			e.preventDefault();
			loadMonth( nav.getAttribute( 'data-month' ), true );
			return;
		}

		var chip = e.target.closest( '.vev-cal-event' );
		if ( chip ) {
			e.preventDefault();
			if ( didDrag ) {
				return;
			}
			var data;
			try {
				data = JSON.parse( chip.getAttribute( 'data-event' ) );
			} catch ( err ) {
				window.location.href = chip.href;
				return;
			}
			openPopover( chip, data );
			return;
		}

		var cell = e.target.closest( '.vev-cal-day' );
		if ( cell && ! cell.classList.contains( 'vev-cal-day--empty' ) && cell.getAttribute( 'data-date' ) ) {
			openCreate( cell );
		}
	} );

	app.addEventListener( 'dragstart', onDragStart );
	app.addEventListener( 'dragend', onDragEnd );
	app.addEventListener( 'dragover', onDragOver );
	app.addEventListener( 'dragleave', onDragLeave );
	app.addEventListener( 'drop', onDrop );

	document.addEventListener( 'click', function ( e ) {
		if ( popover && ! popover.contains( e.target ) && ! e.target.closest( '.vev-cal-event' ) && ! e.target.closest( '.vev-cal-day' ) ) {
			closePopover();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			closePopover();
		}
	} );
}() );
