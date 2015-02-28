'use strict'
// -----------------------------------------------------------------------
//  Whatsspy tracker
//  @Author Maikel Zweerink
//  app.js - AngularJS application
// -----------------------------------------------------------------------

angular.module('whatsspy', ['ngRoute', 'ngVis', 'whatsspyFilters', 'whatsspyControllers', 'angularMoment', 'nvd3ChartDirectives'])
.config(function($routeProvider, $locationProvider) {
  $routeProvider
  .when('/overview', {
    templateUrl: 'overview.html',
    controller: 'OverviewController'
  })
  .when('/compare', {
    templateUrl: 'compare.html',
    controller: 'CompareController'
  })
  .when('/timeline', {
    templateUrl: 'timeline.html',
    controller: 'TimelineController'
  })
  .when('/statistics', {
    templateUrl: 'statistics.html',
    controller: 'StatisticsController'
  })
  .when('/about', {
    templateUrl: 'about.html',
    controller: 'AboutController'
  })
  .otherwise({redirectTo: '/overview'});;
})
.controller('MainController', function($scope, $rootScope, $location, $http, $q, $filter) {
  // Version of the application
  $rootScope.version = '1.3.2';

  $('[data-toggle="tooltip"]').tooltip();

  // Set active buttons according to the current page
  $scope.getActivePageClass = function(path) {
    if ($location.path().substr(1, path.length) == path) {
      return "active-option";
    } else {
      return "";
    }
  }

  // This data is required for this whole Angularjs Application
  $rootScope.accounts = [];
  $rootScope.pendingAccounts = [];
  $rootScope.profilePicPath = null;
  $rootScope.userNotificationPhonenumber = null;
  $rootScope.trackerStart = null;
  $rootScope.loadedTime = null;
  $rootScope.newestVersion = null;
  $rootScope.help = null;
  // Information that might be lazy loaded.
  $rootScope.accountData = {};

  // Tracker online/offline info.
  $rootScope.tracker = [];

  // Just some feedback when setting up WhatsSpy
  $rootScope.error = false;

  $rootScope.getAccounts = function() {
    var deferred = $q.defer();
    $http({method: 'GET', url: 'api/?whatsspy=getStats'}).
      success(function(data, status, headers, config) {
        if(typeof data == 'string') {
          alertify.error('An error occured, please check your configuration:'+data);
          $rootScope.error = true;
        } else {
          $rootScope.accounts = data.accounts;
          $rootScope.pendingAccounts = data.pendingAccounts;
          $rootScope.tracker = data.tracker;
          $rootScope.trackerStart = data.trackerStart;
          $rootScope.profilePicPath = data.profilePicPath;
          $rootScope.userNotificationPhonenumber = data.userNotificationPhonenumber;
          $rootScope.loadedTime = moment();
        }
        deferred.resolve(null);
      }).
      error(function(data, status, headers, config) {
        alertify.error('An error occured, please check your configuration.');
        $rootScope.error = true;
        deferred.reject(null);
      });
    return deferred.promise;
  }

  $rootScope.getAbout = function() {
  var deferred = $q.defer();
  $http({method: 'GET', url: 'api/?whatsspy=getAbout'}).
    success(function(data, status, headers, config) {
      $rootScope.newestVersion = data.version;
      $rootScope.help = data.help;
      deferred.resolve(null);
    }).
    error(function(data, status, headers, config) {
      deferred.reject(null);
    });
  return deferred.promise;
  }

  $rootScope.trackerStatus = function() {
    if($rootScope.tracker == null || $rootScope.tracker[0] == undefined) {
      return 'offline';
    } else if($rootScope.tracker[0].end == null) {
      return 'online';
    } else {
      return 'offline';
    }
  }

  // Some broad used functions
  $rootScope.loadDataFromNumber = function($number) {
    $rootScope.showLoader = true;
    var promises = [];
    promises[0] = $rootScope.loadDataCall($number, null);

    $q.all(promises).then(function(greeting) {
      $rootScope.showLoader = false;
    }, function(reason) {
      $rootScope.showLoader = false;
    }, function(update) {
      //do nothing
    });
  }

  $rootScope.loadDataCall = function($number) {
    var deferred = $q.defer();
    $http({method: 'GET', url: 'api/?whatsspy=getContactStats&number='+$number.id}).
      success(function(data, status, headers, config) {
        if($rootScope.accountData[$number.id] == undefined) {
          $rootScope.accountData[$number.id] = {};
        }
        $rootScope.accountData[$number.id].id = data[0].id;
        $rootScope.accountData[$number.id].user = data[0].user;
        $rootScope.accountData[$number.id].status = data[0].status;
        $rootScope.accountData[$number.id].statusmessages = data[0].statusmessages;
        $rootScope.accountData[$number.id].pictures = data[0].pictures;
        // Setup data structures for the GUI
        $rootScope.accountData[$number.id].generated = {};
        $rootScope.accountData[$number.id].generated.chart_weekday_status_count_all = $rootScope.setupBarChartData([{key: 'today', id: 'dow', value: 'count', data: data[0].advanced_analytics.weekday_status_today},
                                                                                                                    {key: '7 days', id: 'dow', value: 'count', data: data[0].advanced_analytics.weekday_status_7day},
                                                                                                                    {key: '14 days', id: 'dow', value: 'count', data: data[0].advanced_analytics.weekday_status_14day},
                                                                                                                    {key: 'all time', id: 'dow', value: 'count', data: data[0].advanced_analytics.weekday_status_all}]);
        $rootScope.accountData[$number.id].generated.chart_hour_status_count_all = $rootScope.setupBarChartData([{key: 'today', id: 'hour', value: 'count', data: data[0].advanced_analytics.hour_status_today},
                                                                                                                 {key: '7 days', id: 'hour', value: 'count', data: data[0].advanced_analytics.hour_status_7day},
                                                                                                                 {key: '14 days', id: 'hour', value: 'count', data: data[0].advanced_analytics.hour_status_14day},
                                                                                                                 {key: 'all time', id: 'hour', value: 'count', data: data[0].advanced_analytics.hour_status_all}]);
        $rootScope.accountData[$number.id].generated.chart_weekday_status_time_all = $rootScope.setupBarChartData([{key: 'today', id: 'dow', value: 'minutes', data: data[0].advanced_analytics.weekday_status_today},
                                                                                                                   {key: '7 days', id: 'dow', value: 'minutes', data: data[0].advanced_analytics.weekday_status_7day},
                                                                                                                   {key: '14 days', id: 'dow', value: 'minutes', data: data[0].advanced_analytics.weekday_status_14day},
                                                                                                                   {key: 'all time', id: 'dow', value: 'minutes', data: data[0].advanced_analytics.weekday_status_all}]);
        $rootScope.accountData[$number.id].generated.chart_hour_status_time_all = $rootScope.setupBarChartData([{key: 'today', id: 'hour', value: 'minutes', data: data[0].advanced_analytics.hour_status_today},
                                                                                                                {key: '7 days', id: 'hour', value: 'minutes', data: data[0].advanced_analytics.hour_status_7day},
                                                                                                                {key: '14 days', id: 'hour', value: 'minutes', data: data[0].advanced_analytics.hour_status_14day},
                                                                                                                {key: 'all time', id: 'hour', value: 'minutes', data: data[0].advanced_analytics.hour_status_all}]);
        // Set default view
        $rootScope.accountData[$number.id].generated.showHour = false;
        $rootScope.accountData[$number.id].generated.showWeekday = true;

        $rootScope.$broadcast('statusForNumberLoaded', $number);
        deferred.resolve(null);
      }).
      error(function(data, status, headers, config) {
        deferred.reject(null);
      });
    return deferred.promise;
  }

  $rootScope.setupBarChartData = function($data) {
    var $dataSets = [];
    for (var i = 0; i < $data.length; i++) {
      var $values = [];
      for (var y = 0; y < $data[i].data.length; y++) {
        // This is unreadable but:
        // Push an array with (id), (value)
        // So for example DOW 0, 100 online statuses
        $values.push([$data[i].data[y][$data[i].id], $data[i].data[y][$data[i].value]]);
      };
      $dataSets.push({key: $data[i].key, values: $values});
    }
    return $dataSets;
  }

  // Bar chart

  $rootScope.barChartToolTip = function(value, type) {
    if(value == 'weekday') {
      return function(key, x, y, e, graph) {
        var tooltip = '<strong class="whatsspy-bar-chart-head">('+key+') ' + $filter('weekdayToName')(x) + '</strong><br />';
        if(type == 'times') {
          tooltip += '<span class="whatsspy-bar-chart-content">opened ' +  y.substring(0, y.length -2) + ' times.</span>';
        } else {
          tooltip += '<span class="whatsspy-bar-chart-content">' +  y.substring(0, y.length -2) + ' minutes.</span>';
        }
        return tooltip;   
      }
    } else if(value == 'hour') {
      return function(key, x, y, e, graph) {
        var tooltip = '<strong class="whatsspy-bar-chart-head">('+key+') ' + x + ':00 - '+ x +':59</strong><br />';
        if(type == 'times') {
          tooltip += '<span class="whatsspy-bar-chart-content">opened ' +  y.substring(0, y.length -2) + ' times.</span>';
        } else {
          tooltip += '<span class="whatsspy-bar-chart-content">' +  y.substring(0, y.length -2) + ' minutes.</span>';
        }
        return tooltip;
      }
    }
  }


  // Get all the required information
  $rootScope.refreshContent = function() {
    $rootScope.showLoader = true;
    var promises = [];
    promises[0] = $rootScope.getAccounts();

    if($rootScope.help == null) {
      promises[1] = $rootScope.getAbout();
    }

    if($rootScope.inStatsPage == true) {
      promises[2] = $rootScope.loadGlobalStats();
    }
    // Load any new status
    if(Object.keys($rootScope.accountData).length > 0) {
      var k;
      for (k in $rootScope.accountData) {
        if (Object.prototype.hasOwnProperty.call($rootScope.accountData, k)) {
          $rootScope.loadDataCall($rootScope.accountData[k]);
        }
      }
    }

    $q.all(promises).then(function(greeting) {
      $rootScope.showLoader = false;
    }, function(reason) {
      $rootScope.showLoader = false;
    }, function(update) {
    // nothing to do
    });
  }

  // Call the setup
  $scope.refreshContent();

});