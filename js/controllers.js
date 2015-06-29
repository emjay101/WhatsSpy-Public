'use strict'
// -----------------------------------------------------------------------
//	@Name WhatsSpy Public
// 	@Author Maikel Zweerink
//  controllers.js - Controllers for the AngularJS application
// -----------------------------------------------------------------------

angular.module('whatsspyControllers', [])
.controller('OverviewController', function($rootScope, $q, $scope, $http, $timeout, VisDataSet, $filter, $routeParams, $location, $anchorScroll) {
	// Add new number
	$scope.newContact = {'countryCode': '0031', 'number': null, 'name': null};

	// Edit name
	$scope.editContact = {'id': null, 'name': null, 'groups': null, 'read_only_token': null, 'notify_status': null, 'notify_statusmsg': null, 'notify_profilepic': null, 'notify_timeline': null};

	// New group
	$scope.newGroup = {'name': null};
	$scope.filterGroup = null;

	// Javascript page setup call
	$('[data-toggle="tooltip"]').tooltip();

	$scope.$on('$routeChangeStart', function(next, current) { 
		// Dump loaded data, this causes why to long load times when the user goes back to this page.
		$rootScope.accountData = [];
		
	});

	// Check if account is set
	if($location.hash() != null) {
		$scope.hightLightAccount = $location.hash();
	}

	// Functions
	$scope.getAccountWithToken = function(token) {
		$http({method: 'GET', url: 'api/?whatsspy=getStats&token='+token}).
      success(function(data, status, headers, config) {
          if(data.error != null) {
            if(data.code == 403) {
            	$scope.tokenInvalid = true;
            } else {
	            alertify.error(data.error);
	        }
          } else {
            $rootScope.accounts = data.accounts;
            $rootScope.groups = data.groups;
            $scope.toggleContactPanel(data.accounts[0]);
          }
      }).
      error(function(data, status, headers, config) {
        alertify.error('An error occured, please check your configuration.');
        $rootScope.error = true;
      });
	}

	// Get token account
	if($routeParams.token != null) {
		$rootScope.tokenAuth = $routeParams.token;
		if($rootScope.authenticated == true) {
			// logout
			$rootScope.doLogout(false);
		}
		$rootScope.constructor();
		$scope.getAccountWithToken($rootScope.tokenAuth);
	}

	$scope.setNumberInactive = function(contactId) {
		$http({method: 'GET', url: 'api/?whatsspy=setContactInactive&number=' + contactId}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("+" + data.number + " set inactive!");
					$('#editName').modal('hide');
					$scope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.deleteAccountConfirm = function(contactId) {
		var confirm = window.confirm('Are you sure you want to delete this Account?');
		if(confirm == true) {
			$scope.deleteAccount(contactId);
		} 
	}

	$scope.deleteAccount = function(contactId) {
		$http({method: 'GET', url: 'api/?whatsspy=deleteContact&number=' + contactId}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("+" + data.number + " removed!");
					$('#editName').modal('hide');
					$scope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.toggleContactPanel = function($number) {
		if($rootScope.accountData[$number.id] == undefined) {
			$rootScope.loadDataFromNumber($number);
			$rootScope.accountData[$number.id] = {'showPanel': true};
		} else if($rootScope.accountData[$number.id] != undefined && $rootScope.accountData[$number.id].showPanel == false) {
			$rootScope.accountData[$number.id].showPanel = true;
		} else {
			$rootScope.accountData[$number.id].showPanel = false;
		}
	}

	$scope.setEditContact = function($contact) {
		$scope.editContact.id = $contact.id;
		$scope.editContact.name = $contact.name;
		$scope.editContact.read_only_token = $contact.read_only_token;
		$scope.editContact.notify_status = $contact.notify_status;
		$scope.editContact.notify_statusmsg = $contact.notify_statusmsg;
		$scope.editContact.notify_profilepic = $contact.notify_profilepic;
		$scope.editContact.notify_privacy = $contact.notify_privacy;
		$scope.editContact.notify_timeline = $contact.notify_timeline;
		$scope.editContact.groups = $contact.groups;
	}

	$scope.resetObject = function(obj) {
		for (var property in obj) {
          if (obj.hasOwnProperty(property)) {
            	obj[property] = null;
            }
          }
	}

	$scope.editNameModal = function($contact) {
		$scope.setEditContact($contact);
		$('#editName').modal('show');
	}

	$scope.submitAccountEdit = function() {
		var groupArray = '';
		if($scope.editContact.groups != undefined) {
		    for (var i = $scope.editContact.groups.length - 1; i >= 0; i--) {
				groupArray= groupArray+$scope.editContact.groups[i].gid+',';
			};
		    groupArray = groupArray.substring(0, groupArray.length - 1);
		}

		$http({method: 'GET', url: 'api/?whatsspy=updateAccount&number=' + $scope.editContact.id + '&name=' + encodeURIComponent($scope.editContact.name) + '&notify_status=' + $scope.editContact.notify_status + '&notify_statusmsg=' + $scope.editContact.notify_statusmsg + '&notify_profilepic=' + $scope.editContact.notify_profilepic + '&notify_privacy='+ $scope.editContact.notify_privacy +'&notify_timeline=' + $scope.editContact.notify_timeline + '&groups=' + groupArray}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("Contact updated");
					$('#editName').modal('hide');
					$scope.resetObject($scope.editContact);
					$scope.refreshContent(true);
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.submitNewAccount = function() {
		var groupArray = '';
		if($scope.newContact.groups != undefined) {
		    for (var i = $scope.newContact.groups.length - 1; i >= 0; i--) {
				groupArray= groupArray+$scope.newContact.groups[i].gid+',';
			};
		    groupArray = groupArray.substring(0, groupArray.length - 1);
		}

		$http({method: 'GET', url: 'api/?whatsspy=addContact&number=' + $scope.newContact.number + '&countrycode=' + $scope.newContact.countryCode + '&name=' + encodeURIComponent($scope.newContact.name) + '&groups=' + groupArray}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("Contact added to WhatsSpy. Tracking will start in 5-10 minutes.");
					$('#addNumber').modal('hide');
					$scope.newContact.number = null;
					$scope.newContact.name = null;
					$rootScope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.submitNewGroup = function() {
		$http({method: 'GET', url: 'api/?whatsspy=addGroup&name=' + encodeURIComponent($scope.newGroup.name)}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("New group added.");
					$scope.resetObject($scope.newGroup);
					$rootScope.refreshContent(true);
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.deleteGroup = function(gid) {
		$http({method: 'GET', url: 'api/?whatsspy=deleteGroup&gid=' + gid}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("Group removed.");
					$rootScope.refreshContent(true);
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.getGroupUsers = function(gid) {
		var count = 0;
		for (var i = $rootScope.accounts.length - 1; i >= 0; i--) {
			for (var y = $rootScope.accounts[i].groups.length - 1; y >= 0; y--) {
				if($rootScope.accounts[i].groups[y].gid == gid) {
					count++;
				}
			};
		};
		return count;
	}

	$scope.getRealGroups = function() {
		return $filter('noGroupFilter')($rootScope.groups);
	}

	$scope.submitConfigEdit = function() {
		$http({method: 'GET', url: 'api/?whatsspy=updateConfig&account_show_timeline_length=' + $rootScope.config.account_show_timeline_length + '&account_show_timeline_tracker=' + $rootScope.config.account_show_timeline_tracker}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("Configuration updated");
					$('#managePerformance').modal('hide');
					$scope.refreshContent(false);
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.$on('statusForNumberLoaded', function (event, $number) {
	  	$scope.setupTimelineDataForNumber($number);
	});

	$scope.loadTimelineManually = function($number) {
		$scope.setupTimelineDataForNumber($number);
	}


	// Timeline setup
	// Angular-vis.js - This needs to be cleaned
	var graph2d;


	// ------------------------------------------------
	// Event Handlers Timeline

	$scope.onLoaded = function (graphRef) {
		graph2d = graphRef;
		graph2d.setWindow($scope.startTime, $scope.stopTime, false);
		};

		$scope.setWindow = function (window) {
		var periodStart = moment().subtract(1, window);
		$scope.timeNow = moment().valueOf();

		if (graph2d === undefined) {
			return;
		}

		graph2d.setOptions({max: $scope.timeNow});
		graph2d.setWindow(periodStart, $scope.timeNow, false);
	};

	$scope.setNow = function (direction) {
		var range = graph2d.getWindow();
		var interval = range.end - range.start;
		$scope.timeNow = moment().valueOf();

		if (graph2d === undefined) {
			return;
		}

		graph2d.setOptions({max: $scope.timeNow});
		graph2d.setWindow($scope.timeNow - interval, $scope.timeNow, false);
	};

	$scope.stepWindow = function (direction) {
		var percentage = (direction > 0) ? 0.2 : -0.2;
		var range = graph2d.getWindow();
		var interval = range.end - range.start;

		if (graph2d === undefined) {
			return;
		}

		graph2d.setWindow({
		start: range.start.valueOf() - interval * percentage,
		end: range.end.valueOf() - interval * percentage
		});
	};

	$scope.zoomWindow = function (percentage) {
		var range = graph2d.getWindow();
		var interval = range.end - range.start;

		if (graph2d === undefined) {
			return;
		}

		graph2d.setWindow({
		start: range.start.valueOf() - interval * percentage,
		end: range.end.valueOf() + interval * percentage
		});
	};

	$scope.setDateRange = function () {
		$scope.timeNow = moment().valueOf();

		if (graph2d === undefined) {
			return;
		}

		graph2d.setOptions({max: $scope.timeNow});
		graph2d.setWindow($scope.startTime, $scope.stopTime, false);
	};

	/**
	* Callback from the chart whenever the range is updated
	* This is called repeatedly during zooming and scrolling
	* @param period
	*/
	$scope.onRangeChange = function (period) {
	function splitDate(date) {
	var m = moment(date);
	return {
			year: m.get('year'),
			month: {
			number: m.get('month'),
			name: m.format('MMM')
		},
			week: m.format('w'),
			day: {
			number: m.get('date'),
			name: m.format('ddd')
		},
			hour: m.format('HH'),
			minute: m.format('mm'),
			second: m.format('ss')
	};
	}

	var p = {
	s: splitDate(period.start),
	e: splitDate(period.end)
	};

	// Set the window for so the appropriate buttons are highlighted
	// We give some leeway to the interval -:
	// A day, +/- 1 minutes
	// A week, +/- 1 hour
	// A month is between 28 and 32 days
	var interval = period.end - period.start;
	if (interval > 86340000 && interval < 86460000) {
		$scope.graphWindow = 'day';
	} else if (interval > 601200000 && interval < 608400000) {
		$scope.graphWindow = 'week';
	} else if (interval > 2419200000 && interval < 2764800000) {
		$scope.graphWindow = 'month';
	} else {
		$scope.graphWindow = 'custom';
	}

	if (p.s.year == p.e.year) {
		$scope.timelineTimeline =
			p.s.day.name + ' ' + p.s.day.number + '-' + p.s.month.name + '  -  ' +
			p.e.day.name + ' ' + p.e.day.number + '-' + p.e.month.name + ' ' + p.s.year;

		if (p.s.month.number == p.e.month.number) {
			$scope.timelineTimeline =
				p.s.day.name + ' ' + p.s.day.number + '  -  ' +
				p.e.day.name + ' ' + p.e.day.number + ' ' +
				p.s.month.name + ' ' + p.s.year;

			if (p.s.day.number == p.e.day.number) {
				if (p.e.hour == 23 && p.e.minute == 59 && p.e.second == 59) {
					p.e.hour = 24;
					p.e.minute = '00';
					p.e.second = '00';
				}

			$scope.timelineTimeline =
				p.s.hour + ':' + p.s.minute + '  -  ' +
				p.e.hour + ':' + p.e.minute + ' ' +
				p.s.day.name + ' ' + p.s.day.number + ' ' + p.s.month.name + ' ' + p.s.year;
			}
		}
	} else {
		$scope.timelineTimeline =
		p.s.day.name + ' ' + p.s.day.number + '-' + p.s.month.name + ', ' + p.s.year + '  -  ' +
		p.e.day.name + ' ' + p.e.day.number + '-' + p.e.month.name + ', ' + p.e.year;
	}

	// Call apply since this is updated in an event and angular may not know about the change!
	if (!$scope.$$phase) {
			$timeout(function () {
			$scope.$apply();
		}, 0);
		}
	};

	/**
	* Callback from the chart whenever the range is updated
	* This is called once at the end of zooming and scrolling
	* @param period
	*/
	$scope.onRangeChanged = function (period) {
	// nothing

	};


	// Append state data to the timelines

	$scope.setupTimelineDataForNumber = function($number) {

		// The Vis Group dataset (only one group: Status)
		var groups = new VisDataSet();
		// Get the items in place
		var items = new VisDataSet();

		//var fromDate = moment().subtract($rootScope.accountData[$number.id].status_length, 'days');

		groups.add({id: 0, content: 'History view: <br />' + $rootScope.accountData[$number.id].status_length + ' day(s)'});
		// Ignore empty sets
		if($rootScope.accountData[$number.id].status != null) {
			// Add user statusses
			for(var y = 0; y < $rootScope.accountData[$number.id].status.length; y++) {
				var startDate = moment($rootScope.accountData[$number.id].status[y].start);
				var endDate = moment();
				if($rootScope.accountData[$number.id].status[y].end != null) {
					endDate = moment($rootScope.accountData[$number.id].status[y].end);
				}
				items.add({
					id: 'status-'+y,
					group: 0,
					content: '<strong>Online</strong><br />' + startDate.format('HH:mm:ss') + '<br />' + endDate.format('HH:mm:ss'),
					style: 'font-size:11px; line-height: 1;',
					start: startDate.valueOf(),
					end: endDate.valueOf(),
					title: 'Duration: ' + $filter('timeFormat')(Math.round((endDate - startDate)/1000)) + '.',
					type: 'box'
				});
			}
			// Add tracker online status as background
			if($rootScope.tracker != null && $rootScope.tracker.length != 0 && $rootScope.config.account_show_timeline_tracker == true) {
				for(var z = 0; z < $rootScope.tracker.length; z++) {
					var startDate = moment($rootScope.tracker[z].start);
					var endDate = moment();
					if($rootScope.tracker[z].end != null) {
						endDate = moment($rootScope.tracker[z].end);
					}
					items.add({
					id: 'tracker-'+z,
						group: 0,
						start: startDate.valueOf(),
						end: endDate.valueOf(),
						type: 'background'
					});
				}
			}

			$scope.startTime = moment().valueOf() - 36460000;
			$scope.stopTime = moment().valueOf();
			// Append the data to the number
			$rootScope.accountData[$number.id].timelineData = {
				items: items,
				groups: groups
			};

			$rootScope.accountData[$number.id].timelineLoaded = true;
		}
	}

	// create visualization
	$scope.timelineOptions = {
		height:"100%",
		orientation: 'top',
		groupOrder: 'content'  // groupOrder can be a property name or a sorting function
	};


	$scope.graphEvents = {
		rangechange: $scope.onRangeChange,
		rangechanged: $scope.onRangeChanged,
		onload: $scope.onLoaded
	};
})
.controller('CompareController', function($scope, $rootScope, $q, $http, $timeout, VisDataSet, $filter) {

	$scope.comparedAccountsIds = [];

	$scope.toggleBatchInsert = false;
	$scope.totalBatchSize = 0;
	$scope.totalBatchProgress = 0;

	$rootScope.inComparePage = true;
	$scope.$on('$routeChangeStart', function(next, current) { 
		$rootScope.inComparePage = false;
	});

	// Javascript page setup call
	$('[data-toggle="tooltip"]').tooltip();

	$scope.isNumberInComparison = function(id) {
		for (var i = 0; i < $scope.comparedAccountsIds.length; i++) {
			if($scope.comparedAccountsIds[i] == id) {
				return true;
			}
		}
		return false;
	}

	$scope.addUserToComparison = function($number) {
		if($scope.isNumberInComparison($number.id)) {
			alertify.error($number.name + " is already in the comparison!");
			if($scope.toggleBatchInsert == true) {
				$scope.totalBatchProgress++;
			}
		} else {
			$scope.comparedAccountsIds.push($number.id);
			// Retrieve status information
			$rootScope.loadDataFromNumber($number);
		}
	}

	$scope.addGroupToComparison = function(gid) {
		$scope.toggleBatchInsert = true;
		$scope.totalBatchSize = 0;
		$scope.totalBatchProgress = 0;
		for (var i = $rootScope.accounts.length - 1; i >= 0; i--) {
			for (var y = $rootScope.accounts[i].groups.length - 1; y >= 0; y--) {
				if($rootScope.accounts[i].groups[y].gid == gid) {
					$scope.addUserToComparison($rootScope.accounts[i]);
					$scope.totalBatchSize++;
				}
			};
		};
	}

	// broadcast event on status information loaded
	$scope.$on('statusForNumberLoaded', function (event, $number) {
		// Append to timeline
		if($scope.toggleBatchInsert == false) {
		  	$rootScope.refreshTimelineData();
		} else {
			// Check if this is last batch record.			
			$scope.totalBatchProgress++;
			if($scope.totalBatchProgress == $scope.totalBatchSize) {
				$scope.toggleBatchInsert = false;
				$rootScope.refreshTimelineData();
			}
		}
	});

	$scope.removeFromComparison = function(id) {
		for (var i = 0; i < $scope.comparedAccountsIds.length; i++) {
			if($scope.comparedAccountsIds[i] == id) {
				$scope.comparedAccountsIds.splice(i, 1);
				$rootScope.refreshTimelineData();
			}
		}
		// Delete from the timeline
	}



	// Timeline setup
	// Angular-vis.js - This needs to be cleaned
	var graph2d;


	// ------------------------------------------------
	// Event Handlers Timeline

	$scope.onLoaded = function (graphRef) {
		graph2d = graphRef;
		graph2d.setWindow($scope.startTime, $scope.stopTime, false);
		};

		$scope.setWindow = function (window) {
		var periodStart = moment().subtract(1, window);
		$scope.timeNow = moment().valueOf();

		if (graph2d === undefined) {
			return;
		}

		graph2d.setOptions({max: $scope.timeNow});
		graph2d.setWindow(periodStart, $scope.timeNow, false);
	};

	$scope.setNow = function (direction) {
		var range = graph2d.getWindow();
		var interval = range.end - range.start;
		$scope.timeNow = moment().valueOf();

		if (graph2d === undefined) {
			return;
		}

		graph2d.setOptions({max: $scope.timeNow});
		graph2d.setWindow($scope.timeNow - interval, $scope.timeNow, false);
	};

	$scope.stepWindow = function (direction) {
		var percentage = (direction > 0) ? 0.2 : -0.2;
		var range = graph2d.getWindow();
		var interval = range.end - range.start;

		if (graph2d === undefined) {
			return;
		}

		graph2d.setWindow({
		start: range.start.valueOf() - interval * percentage,
		end: range.end.valueOf() - interval * percentage
		});
	};

	$scope.zoomWindow = function (percentage) {
		var range = graph2d.getWindow();
		var interval = range.end - range.start;

		if (graph2d === undefined) {
			return;
		}

		graph2d.setWindow({
		start: range.start.valueOf() - interval * percentage,
		end: range.end.valueOf() + interval * percentage
		});
	};

	$scope.setDateRange = function () {
		$scope.timeNow = moment().valueOf();

		if (graph2d === undefined) {
			return;
		}

		graph2d.setOptions({max: $scope.timeNow});
		graph2d.setWindow($scope.startTime, $scope.stopTime, false);
	};

	/**
	* Callback from the chart whenever the range is updated
	* This is called repeatedly during zooming and scrolling
	* @param period
	*/
	$scope.onRangeChange = function (period) {
	function splitDate(date) {
	var m = moment(date);
	return {
			year: m.get('year'),
			month: {
			number: m.get('month'),
			name: m.format('MMM')
		},
			week: m.format('w'),
			day: {
			number: m.get('date'),
			name: m.format('ddd')
		},
			hour: m.format('HH'),
			minute: m.format('mm'),
			second: m.format('ss')
	};
	}

	var p = {
	s: splitDate(period.start),
	e: splitDate(period.end)
	};

	// Set the window for so the appropriate buttons are highlighted
	// We give some leeway to the interval -:
	// A day, +/- 1 minutes
	// A week, +/- 1 hour
	// A month is between 28 and 32 days
	var interval = period.end - period.start;
	if (interval > 86340000 && interval < 86460000) {
		$scope.graphWindow = 'day';
	} else if (interval > 601200000 && interval < 608400000) {
		$scope.graphWindow = 'week';
	} else if (interval > 2419200000 && interval < 2764800000) {
		$scope.graphWindow = 'month';
	} else {
		$scope.graphWindow = 'custom';
	}

	if (p.s.year == p.e.year) {
		$scope.timelineTimeline =
			p.s.day.name + ' ' + p.s.day.number + '-' + p.s.month.name + '  -  ' +
			p.e.day.name + ' ' + p.e.day.number + '-' + p.e.month.name + ' ' + p.s.year;

		if (p.s.month.number == p.e.month.number) {
			$scope.timelineTimeline =
				p.s.day.name + ' ' + p.s.day.number + '  -  ' +
				p.e.day.name + ' ' + p.e.day.number + ' ' +
				p.s.month.name + ' ' + p.s.year;

			if (p.s.day.number == p.e.day.number) {
				if (p.e.hour == 23 && p.e.minute == 59 && p.e.second == 59) {
					p.e.hour = 24;
					p.e.minute = '00';
					p.e.second = '00';
				}

			$scope.timelineTimeline =
				p.s.hour + ':' + p.s.minute + '  -  ' +
				p.e.hour + ':' + p.e.minute + ' ' +
				p.s.day.name + ' ' + p.s.day.number + ' ' + p.s.month.name + ' ' + p.s.year;
			}
		}
	} else {
		$scope.timelineTimeline =
		p.s.day.name + ' ' + p.s.day.number + '-' + p.s.month.name + ', ' + p.s.year + '  -  ' +
		p.e.day.name + ' ' + p.e.day.number + '-' + p.e.month.name + ', ' + p.e.year;
	}

	// Call apply since this is updated in an event and angular may not know about the change!
	if (!$scope.$$phase) {
			$timeout(function () {
			$scope.$apply();
		}, 0);
		}
	};

	/**
	* Callback from the chart whenever the range is updated
	* This is called once at the end of zooming and scrolling
	* @param period
	*/
	$scope.onRangeChanged = function (period) {
	// nothing
	};


	// Append state data to the timelines
	$rootScope.refreshTimelineData = function() {
		var items = $rootScope.clone($scope.timelineData.items);
		var groups = $rootScope.clone($scope.timelineData.groups);
		items.clear();
		groups.clear();
		

		for(var x = 0; x < $scope.comparedAccountsIds.length; x++) {
			var $number = $rootScope.getAccountById($scope.comparedAccountsIds[x]);
			groups.add({id: x, content: $number.name});
			if($rootScope.accountData[$number.id] != null && $rootScope.accountData[$number.id] != undefined) {
				for(var y = 0; y < $rootScope.accountData[$number.id].status.length; y++) {
					var startDate = moment($rootScope.accountData[$number.id].status[y].start);
					var endDate = moment();
					var itemClass = x % 6; // 6 styles: 0,1,2,3,4,5
					if($rootScope.accountData[$number.id].status[y].end != null) {
						endDate = moment($rootScope.accountData[$number.id].status[y].end);
					}
					items.add({
						id: 'status-'+$number.id+'-'+y,
						group: x,
						className: 'item'+itemClass,
						content: '<strong>Online</strong><br />' + startDate.format('HH:mm:ss') + '<br />' + endDate.format('HH:mm:ss'),
						style: 'font-size:11px; line-height: 1;',
						start: startDate.valueOf(),
						end: endDate.valueOf(),
						title: 'Duration: ' + $filter('timeFormat')(Math.round((endDate - startDate)/1000)) + '.',
						type: 'box'
					});
				}
			}
			// Add tracker online status as background
			if($rootScope.config.account_show_timeline_tracker == true) {
				for(var z = 0; z < $rootScope.tracker.length; z++) {
					var startDate = moment($rootScope.tracker[z].start);
					var endDate = moment();
					if($rootScope.tracker[z].end != null) {
						endDate = moment($rootScope.tracker[z].end);
					}
					items.add({
						id: 'tracker-'+$number.id+'-'+z,
						group: x,
						start: startDate.valueOf(),
						end: endDate.valueOf(),
						type: 'background'
					});
				}
			}
		}

		// Set the new dataset
		$scope.timelineData.items = items;
		$scope.timelineData.groups = groups;
	}


	$scope.setupTimeline = function() {
		// The Vis Group dataset (only one group: Status)
		var groups = new VisDataSet();
		groups.add({id: 0, content: 'Status'});
		// Get the items in place
		var items = new VisDataSet();

		// Add tracker online status as background
		if($rootScope.config.account_show_timeline_tracker == true) {
			for(var z = 0; z < $rootScope.tracker.length; z++) {
				var startDate = moment($rootScope.tracker[z].start);
				var endDate = moment();
				if($rootScope.tracker[z].end != null) {
					endDate = moment($rootScope.tracker[z].end);
				}
				items.add({
					id: 'tracker-'+z,
					group: 0,
					start: startDate.valueOf(),
					end: endDate.valueOf(),
					type: 'background'
				});
			}
		}

		$scope.startTime = moment().valueOf() - 36460000;
		$scope.stopTime = moment().valueOf();
		// Append the data to the number
		$scope.timelineData = {
			items: items,
			groups: groups
		};
		$scope.timelineLoaded = true;
	}

	// create visualization
	$scope.timelineOptions = {
		height:"100%",
		orientation: 'top',
		groupOrder: 'content'  // groupOrder can be a property name or a sorting function
		};

		$scope.graphEvents = {
		rangechange: $scope.onRangeChange,
		rangechanged: $scope.onRangeChanged,
		onload: $scope.onLoaded
	};


	$rootScope.$watch('tracker', function() {
		if($rootScope.tracker != null && $scope.timelineLoaded != true) {
			$scope.setupTimeline();
		}
	});
	
})
.controller('TimelineController', function($scope, $rootScope, $q, $http, $timeout, $filter, $location) {
	$scope.timelineData = null;
	$scope.lastRequiredSid = 0;
	$rootScope.liveFeed = null;
	$scope.filterGroup = null;

	$scope.notificationPlayer = document.getElementById("notification");
	$scope.notifyAnySound = false;
	$scope.showTrackerInfo = true;

	$scope.$watch('showTrackerInfo', function() {
		$scope.timelineData = null;
		$scope.lastRequiredSid = 0;
		$scope.refreshContent(null);
	});

	$scope.showActivityTimeline = true;
	$scope.showStatusTimeline = true;

	$scope.setTimelineTab = function(tab) {
		if(tab == 'activity') {
			$scope.showActivityTimeline = true;
			$scope.showStatusTimeline = false;
		} else {
			// Status
			$scope.showActivityTimeline = false;
			$scope.showStatusTimeline = true;
		}
	}

	$scope.setStatusToDefault = function($item) {
		$item.new = false;
	}

	$scope.setStatusTimeout = function($item) {
		$timeout(function(){$scope.setStatusToDefault($item);}, 4000);
	}

	$scope.isStatusPresentAndUpdateEnd = function($status) {
		if($scope.timelineData == null) {
			return false;
		} else {
			for (var i = $scope.timelineData.userstatus.length - 1; i >= 0; i--) {
				if($scope.timelineData.userstatus[i].sid == $status.sid) {
					// make sure the end record is set
					if($scope.timelineData.userstatus[i].end == null && $status.end != null) {
						$scope.timelineData.userstatus[i].new = true;
						$scope.setStatusTimeout($scope.timelineData.userstatus[i]);
						$scope.timelineData.userstatus[i].end = $status.end;
						$scope.timelineData.userstatus[i].timediff = $status.timediff;
					}
					return true;
				}
			};
			return false;
		}
	}

	$scope.findLastRequiredSid = function($data) {
		var result = 0;
		for(var i = 0; i < $data.length; i++) {
			if($data[i].end == null) {
				result = $data[i].sid;
			}
		}
		if(result == 0 && $data.length > 0) {
			result = $data[$data.length-1].sid;
		}
		return result;
	}

	$scope.showDesktopNotification = function($obj) {
		// Create icon
		var profile = $rootScope.getAccountById($obj.id);
		var icon = 'images/profile_pic_placeholder.png';
		if(profile != null) {
			icon = $rootScope.getImageURL(profile.profilepic);
		}
		// Create body
		var body = '';
		if($obj.type == undefined) {
			body = $obj.name + ' is now online.';
		} else if($obj.type == 'statusmsg') {
			body = $obj.name + ' has a new status message: ' + $obj['msg_status'] + '.';
		} else if($obj.type == 'profilepic') {
			body = $obj.name + ' has a new profile picture.';
			icon = $rootScope.getImageURL($obj.hash);
		} else if($obj.type == 'lastseen_privacy') {
			var privacy_setting = 'everybody';
			if($obj.lastseen_privacy == true) {
				privacy_setting = 'contacts or nobody';
			}
			body = $obj.name + ' has changed his last seen privacy setting to ' + privacy_setting + '.';
		} else if($obj.type == 'profilepic_privacy') {
			var privacy_setting = 'everybody';
			if($obj.profilepic_privacy == true) {
				privacy_setting = 'contacts or nobody';
			}
			body = $obj.name + ' has changed his profile picture privacy setting to ' + privacy_setting + '.';
		} else if($obj.type == 'statusmsg_privacy') {
			var privacy_setting = 'everybody';
			if($obj.statusmsg_privacy == true) {
				privacy_setting = 'contacts or nobody';
			}
			body = $obj.name + ' has changed his status message privacy setting to ' + privacy_setting + '.';
		} else if($obj.type == 'tracker_start') {
			body = 'Tracker has started tracking.';
			icon = 'app-icon-big.png';
		} else if($obj.type == 'tracker_end') {
			body = 'Tracker has stopped tracking with reason: ' + $obj.name + '.';
			icon = 'app-icon-big.png';
		}

		var notificationObj = { body: body,
						        icon: icon,
						        dir : "ltr" };

		if (Notification.permission === "granted") {
		  var notification = new Notification('WhatsSpy Public', notificationObj);
		  setTimeout(function(){
				notification.close();
			},8000);
		} else if (Notification.permission !== 'denied') {
			Notification.requestPermission(function (permission) {
				if (!('permission' in Notification)) {
				Notification.permission = permission;
				}

				if (permission === "granted") {
					var notification = new Notification('WhatsSpy Public', notificationObj);
					setTimeout(function(){
						notification.close();
					},8000);
				}
			});
		}
	}

	$scope.notifyForObj = function($obj) {
		if(($scope.notifyAnySound == true || $obj.notify_timeline == true) && $filter('numberFilter')([$obj], $scope.filterPhonenumber, $scope.filterName, $scope.filterGroup).length  != 0) {
			try {
				$scope.showDesktopNotification($obj);
				$scope.notificationPlayer.play();
			} catch(e) {
				console.log(e);
			}
		}
	}



	$scope.appendToTimelineFront = function($data) {
		// Activities
		if($data.activity != null) {
			for(var i = 0; i < $data.activity.length; i++) {
				// Add UI feedback
				$data.activity[i].new = true;
				$scope.setStatusTimeout($data.activity[i]);
				$scope.timelineData.activity.unshift($data.activity[i]);
				$scope.notifyForObj($data.activity[i]);
			}
		}
		// Userstatus
		if($data.userstatus != null) {
			for(var i = 0; i < $data.userstatus.length; i++) {
				// Do not add overlap again
				// This might be a existing record which we want to update the "end" status.
				if(!$scope.isStatusPresentAndUpdateEnd($data.userstatus[i])) {
					// Add UI feedback
					$data.userstatus[i].new = true;
					$scope.setStatusTimeout($data.userstatus[i]);
					// Add first record
					$scope.timelineData.userstatus.unshift($data.userstatus[i]);
					// Remove last record
					if($scope.timelineData.userstatus.length > 200) {
						$scope.timelineData.userstatus.pop();
					}
					$scope.notifyForObj($data.userstatus[i]);
				} 
				if($data.userstatus[i].end == null) {
					$scope.lastRequiredSid = $data.userstatus[i].sid;
				}
			}
		}

		$scope.timelineData.till = $data.till;
	}

	// This function is only called for activities, not statuses
	$scope.appendToTimelineBack = function($data) {
		// Activities
		for(var i = 0; i < $data.activity.length; i++) {
			$scope.timelineData.activity.push($data.activity[i]);
		}
		$scope.timelineData.since = $data.since;
	}

	$scope.requestOlderActivityData = function() {
		if($scope.timelineData != null) {
			$scope.refreshContent('&activities_till='+$scope.timelineData.since);
		}
	}

	$scope.$on('$routeChangeStart', function(next, current) { 
		// Cancel timer
		if($rootScope.liveFeed != null) {
			$timeout.cancel($rootScope.liveFeed);
		}
	});

	$scope.loadDataTimeLine = function(query) {
		var deferred = $q.defer();
		if(query === null) {
			query = '';
		}
		if($scope.showTrackerInfo == false) {
			query += '&showTrackerInfo=false';
		}
		$http({method: 'GET', url: 'api/?whatsspy=getTimelineStats' + query}).
		success(function(data, status, headers, config) {
			if(data.error != null) {
				if(data.code == 403) {
		            $location.path('/login');
		            $rootScope.constructor();
		            $rootScope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			} else {
				// init load (type=init)
				if($scope.timelineData == null) {
					$scope.timelineData = data;
					$scope.lastRequiredSid = $scope.findLastRequiredSid($scope.timelineData.userstatus);
					$rootScope.liveFeed = $timeout($scope.liveTimeline, 5000);
				// update or history load
				} else {
					// Load to the end of activities
					if(data.type == 'activities_till') {
						$scope.appendToTimelineBack(data);
					// Load to front of activities & statuses (type=since)
					} else {
						$scope.appendToTimelineFront(data);
					}
				}
			}
			deferred.resolve(null);
		}).
		error(function(data, status, headers, config) {
			deferred.reject(null);
		});
		return deferred.promise;
	}

	// Get all the required information
	$scope.refreshContent = function(query) {
		$rootScope.showLoader = true;
		var promises = [];
		promises[0] = $scope.loadDataTimeLine(query);

		$q.all(promises).then(function(greeting) {
		$rootScope.showLoader = false;
		}, function(reason) {
			$rootScope.showLoader = false;
		}, function(update) {
		// nothing to do
		});
	}

	// Call the setup
	//$scope.refreshContent(null);

	$scope.liveTimeline = function() {
		$scope.refreshContent('&activities_since='+$scope.timelineData.till+'&sid_status='+$scope.lastRequiredSid);
		$rootScope.liveFeed = $timeout($scope.liveTimeline, 5000);
	}
})
.controller('StatisticsController', function($rootScope, $q, $scope, $http, $filter, $routeParams) {
	$scope.stats = null;
	$scope.filterGroup = null;
	$rootScope.tokenAuth = null;

	$scope.showTopUsers = 10;

	if($routeParams.token != null) {
		if($rootScope.authenticated == true) {
			// logout
			$rootScope.doLogout(false);
		}
		$rootScope.constructor();
		$rootScope.tokenAuth = $routeParams.token;
	}

	$rootScope.inStatsPage = true;
	$scope.$on('$routeChangeStart', function(next, current) { 
		$rootScope.inStatsPage = false;
	});

	$scope.$watch('filterGroup', function() {
		if($scope.showTopUsers != 10) {
			$scope.showTopUsers = 10;
		} else {
			$scope.refreshContent();
		}
	});

	$scope.$watch('showTopUsers', function() {
		$scope.refreshContent();
	});



	/**
    * d3.js functions to read dataset created in app.js
    */
	$scope.xFunction = function(){
		return function(d) {
			return d.name;
		};
	}

	$scope.yFunction = function(prop){
		return function(d) {
			return d[prop];
		};
	}

	$scope.tooltipUserStatusCount = function(prop) {
		return function(key, x, y, e, graph) {
			return  '<h4 class="whatsspy-stat-head">' + key + '</h4>' +
		        '<p>' +  y.point[prop] + ' times</p>'
		}
	}

	$scope.tooltipUserStatusTime = function(prop) {
		return function(key, x, y, e, graph) {
			return  '<h4 class="whatsspy-stat-head">' + key + '</h4>' +
		        '<p>' +  $filter('timeFormat')(y.point[prop]) + '</p>'
		}
	}

	$rootScope.loadGlobalStats = function(component) {
		var query = '';
		if($rootScope.tokenAuth != null) {
			query = '&token='+$rootScope.tokenAuth;
		}
		if(component == 'top_usage_users') {
			query = query + '&users='+$scope.showTopUsers;
		}
		var deferred = $q.defer();
		$http({method: 'GET', url: 'api/?whatsspy=getGlobalStats&component='+component+'&group='+$scope.filterGroup + query}).
		success(function(data, status, headers, config) {
			if(data.error != null) {
				if(data.code == 403) {
					$scope.tokenInvalid = true;
				} else {
					alertify.error(data.error);
				}
			} else {
				if($scope.stats == null) {
					$scope.stats = {};
				}
				$scope.stats[component] = data;

				if(component == 'top_usage_users') {
		        	if($scope.stats.generated == null) {
			        	$scope.stats.generated = {};
			    	}
			    	$scope.stats.generated.top10DayChoice = 'today';
		        	$scope.stats.generated.top10TimeChoice = 'alltime';
				}



				if(component == 'user_status_analytics_time') {
					// Setup data structures for the GUI
					if($scope.stats.generated == null) {
			        	$scope.stats.generated = {};
			    	}
			        $scope.stats.generated.chart_weekday_status_count_all = $rootScope.setupBarChartData([{key: 'today', id: 'dow', value: 'count', data: data.weekday_status_today},
			        																					  {key: '7 days', id: 'dow', value: 'count', data: data.weekday_status_7day},
			                                                                                              {key: '14 days', id: 'dow', value: 'count', data: data.weekday_status_14day},
			                                                                                              {key: 'all time', id: 'dow', value: 'count', data: data.weekday_status_all}]);
			        $scope.stats.generated.chart_hour_status_count_all = $rootScope.setupBarChartData([{key: 'today', id: 'hour', value: 'count', data: data.hour_status_today},
			        																				   {key: '7 days', id: 'hour', value: 'count', data: data.hour_status_7day},
			                                                                                           {key: '14 days', id: 'hour', value: 'count', data: data.hour_status_14day},
			                                                                                           {key: 'all time', id: 'hour', value: 'count', data: data.hour_status_all}]);
			        $scope.stats.generated.chart_weekday_status_time_all = $rootScope.setupBarChartData([{key: 'today', id: 'dow', value: 'minutes', data: data.weekday_status_today},
			        																					 {key: '7 days', id: 'dow', value: 'minutes', data: data.weekday_status_7day},
			                                                                                             {key: '14 days', id: 'dow', value: 'minutes', data: data.weekday_status_14day},
			                                                                                             {key: 'all time', id: 'dow', value: 'minutes', data: data.weekday_status_all}]);
			        $scope.stats.generated.chart_hour_status_time_all = $rootScope.setupBarChartData([{key: 'today', id: 'hour', value: 'minutes', data: data.hour_status_today},
			        																				  {key: '7 days', id: 'hour', value: 'minutes', data: data.hour_status_7day},
			                                                                                          {key: '14 days', id: 'hour', value: 'minutes', data: data.hour_status_14day},
			                                                                                          {key: 'all time', id: 'hour', value: 'minutes', data: data.hour_status_all}]);
			        // Set default view
		        	$scope.stats.generated.showHour = false;
		        	$scope.stats.generated.showWeekday = true;
		        	$scope.stats.generated.showPieChart = 'today';
		        }
		    }

			deferred.resolve(null);
		}).
		error(function(data, status, headers, config) {
			deferred.reject(null);
		});
		return deferred.promise;
	}

	// Get all the required information
	$scope.refreshContent = function() {
		$rootScope.showLoader = true;
		var promises = [];
		promises[0] = $rootScope.loadGlobalStats('global_stats');
		promises[1] = $rootScope.loadGlobalStats('user_status_analytics_user');
		promises[2] = $rootScope.loadGlobalStats('user_status_analytics_time');
		promises[3] = $rootScope.loadGlobalStats('top_usage_users');

		$q.all(promises).then(function(greeting) {
		$rootScope.showLoader = false;
		}, function(reason) {
			$rootScope.showLoader = false;
		}, function(update) {
		// nothing to do
		});
	}

	// No need to call anymore, watch will do this.
	//$scope.refreshContent();
})
.controller('AboutController', function($rootScope, $q, $scope, $http, $location) {
	$scope.executeServerCmd = function(query) {
		var deferred = $q.defer();
		$http({method: 'GET', url: 'api/?whatsspy=' + query}).
		success(function(data, status, headers, config) {
			if(data.error != null) {
				if(data.code == 403) {
		            $location.path('/login');
		            $rootScope.constructor();
		            $rootScope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			} else {
				alertify.log('CMD: '+query+' resulted in code: ' + data['result-code'] + ' with output: ' + data.result.join());
				$rootScope.refreshContent();
			}
			deferred.resolve(null);
		}).
		error(function(data, status, headers, config) {
			deferred.reject(null);
		});
		return deferred.promise;
	}
})
.controller('LoginController', function($rootScope, $q, $scope, $http, $location) {

	$scope.attemptLogin = function() {
		$rootScope.showLoader = true;
		$http({method: 'GET', url: 'api/?whatsspy=doLogin&password=' + encodeURIComponent($scope.inputPassword)}).
			success(function(data, status, headers, config) {
				$rootScope.showLoader = false;
				$scope.inputPassword = null;
				if(data.success == true) {
					$rootScope.authenticated = true;
					$rootScope.tokenAuth = null;
					$rootScope.tokenInvalid = null;
					$location.path('/overview');
					$scope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}
});