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
.filter('weekdayToName', function() {
	return function(weekday) {
		switch (weekday) {
		    case 0:
		        return "Sunday";
		        break;
		    case 1:
		        return "Monday";
		        break;
		    case 2:
		        return "Tuesday";
		        break;
		    case 3:
		        return "Wednesday";
		        break;
		    case 4:
		        return "Thursday";
		        break;
		    case 5:
		        return "Friday";
		        break;
		    case 6:
		        return "Saturday";
		        break;
		}
	}
})
.filter('emptyName', function () {
	return function (value) {
		if(value == null) {
			return 'No name';
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
.filter('notificationPhonenumber', function () {
	return function (value) {
		if(value == null || value == '') {
			return 'not set, please add this to config.php';
		} else {
			return value;
		}
	};
})
.filter('timeFormat', function () {
        return function (seconds) {
            var str = '';
            var remainingSec = seconds;
            if(seconds == null || seconds == undefined) {
              return str;
            } 

            if(remainingSec > 43200) {
              str += Math.floor(remainingSec / 43200) + 'd ';
              remainingSec = remainingSec % 43200;
            }

            if(remainingSec > 3600) {
              str += Math.floor(remainingSec / 3600) + 'h ';
              remainingSec = remainingSec % 3600;
            }

            if(remainingSec > 60) {
              str += Math.floor(remainingSec / 60) + 'min ';
              remainingSec = remainingSec % 60;
            } 

            if(remainingSec > 0) {
              str += remainingSec + 'sec';
            }

            return str;
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
				if(contact.id == null) {
					result = false;
				} else if(contact.id.indexOf(phoneNumber) != 0) {
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