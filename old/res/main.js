// Main site JS blob - should be mostly utilities

// Not jquery		
function $n(id) 
{
	return (typeof id == "string") ? document.getElementById(id) : id;
}

function wait( ms ) {
	return new Promise( function(res, rej) {
		setTimeout( function() {
			res();
		}, ms );
	} );
}

function watchdog( ms ) { /* TODO */ }


/*
=====================
copy
=====================
*/
function copy( thing, depth )
{
    // Handle the 3 simple types, and null or undefined
    if (null === thing || "object" != typeof thing) return thing;

    if (depth && depth !== true) {
    	--depth;
    }

    // Handle Array
    if (thing instanceof Array) {
        if (!depth) {
        	return thing.slice( 0 );
        }
        let res = [];
        for (let i = 0, len = thing.length; i < len; i++) {
            res[i] = copy( thing[i], depth );
        }
        return res;
    }

    // Handle Date
    if (thing instanceof Date) {
    	return new Date(+thing);
    }

    // Handle "simple" objects
    if (thing instanceof Object) {
        let res = {};
        if (!depth) {
	        for (let attr in thing) {
	            if (thing.hasOwnProperty(attr))  res[attr] = thing[attr];
	        }
        } else {
	        for (let attr in thing) {
	            if (thing.hasOwnProperty(attr)) {
	            	res[attr] = copy( thing[attr], depth );
	            }
	        }
        }
        return res;
    }

    // Fallback is to assume that the item is not a container.
    return thing;
}

function loadText( method, url, data )
{
	return new Promise( function( res, rej ) {
		var xhr = new XMLHttpRequest();
		xhr.open(method, url, true);
		xhr.onreadystatechange = function () {
			if (xhr.readyState != XMLHttpRequest.DONE) {
				return;
			}
			if (xhr.status == 200) {
				res( xhr.responseText );
			} else {
				rej( xhr.status );
			}
		};
		xhr.send( data );
	} );
}

function nav( pg )
{
	window.location = pg;
}

function autoload( method, url, data, mask )
{
	if (mask) {
		$n( "loadermask" ).style.display = "block";
	}
	return wait(1).then( function() {
		return loadText( method, url, data );
	} ).then( function( text ) {
		if (mask) {
			$n( "loadermask" ).style.display = "none";
		}
		if (text.length && text[0] == '{') {
			try {
				var d = JSON.parse( text );
				if ("html" in d) {
					for (let k in d.html) {
						if (k[0] == '+') {
							$n( k.substr(1) ).innerHTML += d.html[k];
						} else {
							$n( k ).innerHTML = d.html[k];
						}
					}
				}
				if ("alert" in d) {
					alert(d.alert);
				}
				if ("run" in d) {
					eval(d.run);
				}
				if ("goto" in d) {
					window.location = d.goto;
				}
				if ("at" in d) {
					if (typeof d == "string") {
						d = { url: d };
					}
					window.history.pushState( d.state || {}, 
						d.title || "", d.url );
				}
				return d;
			} catch(e) {
				// fall through to dump response
			}
		}
		return text;
	}, function( rej ) {
		if (mask) {
			$n( "loadermask" ).style.display = "none";
		}
		return Promise.reject( rej );
	} );
}
function submitForm( frm, ev )
{
	if (ev) {
		ev.preventDefault();
	}
	if (typeof frm == "string") {
		frm = $n( frm );
	}
	var fd = new FormData( frm );
	fd.append( "_autoloader_", "1" );
	var will = autoload( 
			frm.getAttribute("method" ), 
			frm.getAttribute("action"), 
			fd,
			true 
	).then( function( r ) { 
		return r;
	}, function( err ) {
		alert( "An error occurred while submitting this form." );
	} );
	if (ev) { return false; }
	return will;
}
function formHijack(ev) {
	return submitForm( this, ev );
}
function setupForms() {
	var fl = document.getElementsByTagName("FORM");
	for (var i = 0; i < fl.length; i++) {
		var f = fl[i];
		if (!f.getAttribute("use-autoloader")) {
			continue;
		}
		f.removeEventListener( 'submit', formHijack );
		f.addEventListener( 'submit', formHijack );
	}
}

window.onload = function() {
	setupForms();
}
