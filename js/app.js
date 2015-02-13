'use strict'
// -----------------------------------------------------------------------
//  Whatsspy tracker, developed by Maikel Zweerink
//  app.js - AngularJS application
// -----------------------------------------------------------------------

angular.module('whatsspy', ['ngRoute', 'ngVis', 'whatsspyFilters', 'whatsspyControllers', 'angularMoment'])
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
  .when('/about', {
    templateUrl: 'about.html',
    controller: 'AboutController'
  })
  .otherwise({redirectTo: '/overview'});;
})
.controller('MainController', function($scope, $rootScope, $location, $http, $q) {
  $rootScope.version = '1.0.6';

  $('[data-toggle="tooltip"]').tooltip();
  // Set active buttons according to the current page
  $scope.getActivePageClass = function(path) {
    if ($location.path().substr(1, path.length) == path) {
      return "active-option";
    } else {
      return "";
    }
  }

  // Get contact data
  // This data is required for this whole Angularjs Application
  $rootScope.accounts = [];
  $rootScope.tracker = [];

  $rootScope.error = false;

  $rootScope.getAccounts = function() {
    var deferred = $q.defer();
    $http({method: 'GET', url: 'api/?whatsspy=getStats'}).
      success(function(data, status, headers, config) {
        $rootScope.accounts = data.accounts;
        $rootScope.pendingAccounts = data.pendingAccounts;
        $rootScope.tracker = data.tracker;
        $rootScope.trackerStart = data.trackerStart;
        $rootScope.profilePicPath = data.profilePicPath;
        $rootScope.loadedTime = moment();

        deferred.resolve(null);
      }).
      error(function(data, status, headers, config) {
        alertify.error('An error occured, please check your configuration.');
        $rootScope.error = true;
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
    promises[0] = $rootScope.loadDataCall($number);

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
        $number.data = data[0];
        $rootScope.$broadcast('statusForNumberLoaded', $number);
        deferred.resolve(null);
      }).
      error(function(data, status, headers, config) {
        deferred.reject(null);
      });
    return deferred.promise;
  }


  // Get all the required information
  $rootScope.refreshContent = function() {
    $rootScope.showLoader = true;
    var promises = [];
    promises[0] = $rootScope.getAccounts();

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