'use strict'
// -----------------------------------------------------------------------
//  Whatsspy tracker, developed by Maikel Zweerink
//  controllers.js - Controllers for the AngularJS application
//
//  Yes, this setup is not very clean. It's suiteable for it's purpose.
// -----------------------------------------------------------------------

angular.module('whatsspyControllers', [])
.controller('OverviewController', function($rootScope, $q, $scope, $http, $route, $routeParams, $location, $timeout, VisDataSet) {

	$scope.filterPhonenumber = null;
	$scope.filterName = null;

	// Add new number
	$scope.newCountryCode = '0031';
	$scope.newPhoneNumber = null;
	$scope.newName = null;

	// Edit name
	$scope.editNameNumber = null;
	$scope.editName = null;

	// Functions
	$scope.setNumberInactive = function(number) {
		$http({method: 'GET', url: 'api/?whatsspy=setContactInactive&number=' + number}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("+" + data.number + " set inactive!");
					$scope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.deleteNumber = function(number) {
		$http({method: 'GET', url: 'api/?whatsspy=deleteContact&number=' + number}).
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

	$scope.editNameModal = function(numberObj) {
		$scope.editNameNumber = numberObj.id;
		$scope.editName = numberObj.name;
		$('#editName').modal('show');
	}

	$scope.submitNameEdit = function() {
		$http({method: 'GET', url: 'api/?whatsspy=updateName&number=' + $scope.editNameNumber + '&name=' + $scope.editName }).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("Contact updated");
					$('#editName').modal('hide');
					$scope.editNameNumber = null;
					$scope.editName = null;
					$scope.refreshContent();
				} else {
					alertify.error(data.error);
				}
			}).
			error(function(data, status, headers, config) {
				alertify.error("Could not contact the server.");
			});
	}

	$scope.submitNewNumber = function() {
		$http({method: 'GET', url: 'api/?whatsspy=addContact&number=' + $scope.newPhoneNumber + '&countrycode=' + $scope.newCountryCode + '&name=' + $scope.newName}).
			success(function(data, status, headers, config) {
				if(data.success == true) {
					alertify.success("Contact added to WhatsSpy. Tracking will start in 5 minutes.");
					$('#addNumber').modal('hide');
					$scope.newPhoneNumber = null;
					$scope.newName = null;
					$rootScope.refreshContent();
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
		groups.add({id: 0, content: 'Status'});
		// Ignore empty sets
		if($number.data.status != null) {
			// Get the items in place
			var items = new VisDataSet();

			for(var y = 0; y < $number.data.status.length; y++) {
				var startDate = moment($number.data.status[y].start);
				var endDate = moment();
				if($number.data.status[y].end != null) {
					endDate = moment($number.data.status[y].end);
				}
				items.add({
					id: 'status-'+y,
					group: 0,
					content: '<strong>Online</strong><br />' + startDate.format('HH:mm:ss') + '<br />' + endDate.format('HH:mm:ss'),
					style: 'font-size:11px; line-height: 1;',
					start: startDate.valueOf(),
					end: endDate.valueOf(),
					title: 'from ' + startDate.format('HH:mm:ss') + ' till ' + endDate.format('HH:mm:ss'),
					type: 'box'
				});
			}
			// Add tracker online status as background
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


			$scope.startTime = moment().valueOf() - 36460000;
			$scope.stopTime = moment().valueOf();
			// Append the data to the number
			$number.data.timelineData = {
				items: items,
				groups: groups
			};
			$number.data.timelineLoaded = true;
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
.controller('CompareController', function($scope, $rootScope, $q, $http, $timeout, VisDataSet) {

	$scope.comparedAccounts = [];

	$scope.isNumberInComparison = function(id) {
		for (var i = 0; i < $scope.comparedAccounts.length; i++) {
			if($scope.comparedAccounts[i].id == id) {
				return true;
			}
		}
		return false;
	}

	$scope.addToComparison = function($number) {
		if($scope.isNumberInComparison($number.id)) {
			alertify.error("Contact is already in the comparison!");
		} else {
			$scope.comparedAccounts.push($number);
			// Retrieve status information
			$rootScope.loadDataFromNumber($number);
		}
	}

	// broadcast event on status information loaded
	$scope.$on('statusForNumberLoaded', function (event, $number) {
		// Append to timeline
	  	$scope.refreshTimelineData($scope.comparedAccounts);
	});

	$scope.removeFromComparison = function($number) {
		for (var i = 0; i < $scope.comparedAccounts.length; i++) {
			if($scope.comparedAccounts[i].id == $number.id) {
				$scope.comparedAccounts.splice(i, 1);
				$scope.refreshTimelineData($scope.comparedAccounts);
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
	$scope.refreshTimelineData = function($numbers) {
		var items = $scope.timelineData.items;
		var groups = $scope.timelineData.groups;
		items.clear();
		groups.clear();

		
		

		for(var x = 0; x < $numbers.length; x++) {
			var $number = $numbers[x];
			groups.add({id: x, content: $number.name});
			if($number.data != null && $number.data != undefined) {
				for(var y = 0; y < $number.data.status.length; y++) {
					var startDate = moment($number.data.status[y].start);
					var endDate = moment();
					var itemClass = x % 6; // 6 styles: 0,1,2,3,4,5
					if($number.data.status[y].end != null) {
						endDate = moment($number.data.status[y].end);
					}
					items.add({
						id: 'status-'+$number.id+'-'+y,
						group: x,
						className: 'item'+itemClass,
						content: '<strong>Online</strong><br />' + startDate.format('HH:mm:ss') + '<br />' + endDate.format('HH:mm:ss'),
						style: 'font-size:11px; line-height: 1;',
						start: startDate.valueOf(),
						end: endDate.valueOf(),
						title: 'from ' + startDate.format('HH:mm:ss') + ' till ' + endDate.format('HH:mm:ss'),
						type: 'box'
					});
				}
			}
			// Add tracker online status as background
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
.controller('TimelineController', function($scope, $rootScope, $q, $http, $timeout) {
	$scope.timelineData = null;
	$rootScope.liveFeed = null;

	$scope.setStatusToDefault = function($item) {
		$item.new = false;
	}

	$scope.setStatusTimeout = function($item) {
		$timeout(function(){$scope.setStatusToDefault($item);}, 4000);
	}

	$scope.appendToTimelineFront = function($data) {
		// Activities
		for(var i = 0; i < $data.activity.length; i++) {
			// Add UI feedback
			$data.activity[i].new = true;
			$scope.setStatusTimeout($data.activity[i]);

			$scope.timelineData.activity.unshift($data.activity[i]);
		}
		// Userstatus
		for(var i = 0; i < $data.userstatus.length; i++) {
			// Add UI feedback
			$data.userstatus[i].new = true;
			$scope.setStatusTimeout($data.userstatus[i]);

			$scope.timelineData.userstatus.unshift($data.userstatus[i]);
		}

		$scope.timelineData.till = $data.till;
	}

	$scope.appendToTimelineBack = function($data) {
		// Activities
		for(var i = 0; i < $data.activity.length; i++) {
			$scope.timelineData.activity.push($data.activity[i]);
		}
		// Userstatus
		for(var i = 0; i < $data.userstatus.length; i++) {
			$scope.timelineData.userstatus.push($data.userstatus[i]);
		}

		$scope.timelineData.since = $data.since;
	}

	$scope.requestOlderData = function() {
		if($scope.timelineData != null) {
			$scope.refreshContent('&till='+$scope.timelineData.since);
		}
	}

	$scope.$on('$routeChangeStart', function(next, current) { 
		// Cancel timer
		if($rootScope.liveFeed != null) {
			$timeout.cancel($rootScope.liveFeed);
		}
	});

	$scope.loadDataTimeLine = function(query, insertBefore) {
		var deferred = $q.defer();
		if($scope.timelineData != null && query == null) {
			query = '&since='+$scope.timelineData.till;
		}
		if(query === null) {
			query = '';
		}
		if(insertBefore == null) {
			insertBefore = true;
		}
		$http({method: 'GET', url: 'api/?whatsspy=getTimelineStats' + query}).
		success(function(data, status, headers, config) {
			if($scope.timelineData == null) {
				$scope.timelineData = data;
			} else {
				if(data.till == undefined) {
					$scope.appendToTimelineBack(data);
				} else {
					$scope.appendToTimelineFront(data);
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
	$scope.refreshContent(null);

	$scope.liveTimeline = function() {
		$scope.refreshContent(null);
		$rootScope.liveFeed = $timeout($scope.liveTimeline, 8000);
	}

	$rootScope.liveFeed = $timeout($scope.liveTimeline, 8000);
})
.controller('AboutController', function($rootScope, $q, $scope, $http) {

});