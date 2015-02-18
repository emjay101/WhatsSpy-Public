'use strict'
// -----------------------------------------------------------------------
//  Whatsspy tracker, developed by Maikel Zweerink
//  filters.js - Filters for the AngularJS application
// -----------------------------------------------------------------------

angular.module('whatsspyFilters', [])
.filter('datetime', function () {
	return function (time) {
		var d = new Date(time);
		var w = moment(d);
		return w.fromNow();
	};
})
.filter('emptyField', function () {
	return function (value) {
		if(value == null || value == undefined || value == '') {
			return '-';
		} else {
			return value;
		}
	};
})
.filter('staticDate', function () {
	return function (time) {
		if(time == null)
			return '-';
		var w = null;
		if(!isNaN(parseFloat(time)) && isFinite(time)) {
			var d = new Date(0);
		d.setUTCSeconds(time);
			var w = moment(d);
		} else {
			var w = moment(time);
		}
		return w.format('DD-MM-YYYY');
	};
})
.filter('staticTime', function () {
	return function (time) {
		if(time == null)
			return '-';
		var w = null;
		if(!isNaN(parseFloat(time)) && isFinite(time)) {
			var d = new Date(0);
			d.setUTCSeconds(time);
			var w = moment(d);
		} else {
			var w = moment(time);
		}
		return w.format('HH:mm:ss');
	};
})
.filter('staticDatetime', function () {
	return function (time) {
		if(time == null)
			return '-';
		var w = null;
		if(!isNaN(parseFloat(time)) && isFinite(time)) {
			var d = new Date(0);
			d.setUTCSeconds(time);
			var w = moment(d);
		} else {
			var w = moment(time);
		}
		return w.format('DD-MM-YYYY HH:mm:ss');
	};
})
.filter('object2Array', function() {
	return function(input) {
		var out = []; 
		for(var i in input){
			out.push(input[i]);
		}
		return out;
	}
})
.filter('emptyName', function () {
	return function (value) {
		if(value == null) {
			return 'Unknown contact';
		} else {
			return value;
		}
	};
})
.filter('privacy', function () {
	return function (value) {
		if(value == false) {
			return '\'everyone\'';
		} else {
			return '\'contacts or nobody\'';
		}
	};
})
.filter('numberFilter', function () {
	return function (contacts, phoneNumber, name) {
		if(contacts == undefined) {
			return null;
		}
		return contacts.filter(function(contact) {
			var result = true;
			if(phoneNumber != null) {
			if(contact.id.indexOf(phoneNumber) != 0) {
				result = false;
			}
			}
			if(name != null) {
				// Multiple searching terms used
				var searchterms = name.split('|');
				var allowName = false;
				for(var i = 0; i < searchterms.length; i++) {
					if(searchterms[i] != '' &&
						contact.name != null &&
						contact.name.toLowerCase().indexOf(searchterms[i].toLowerCase()) != -1) {
						allowName = true;
					}
				}
				if(searchterms.length == 1 && searchterms[0] == '') {
					// We dont care!?!
					result = true;
				} else {
					result = allowName;
				}
			}
			return result;
		});
	};
});