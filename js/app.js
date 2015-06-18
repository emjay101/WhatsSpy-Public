'use strict'
// -----------------------------------------------------------------------
//  @Name WhatsSpy Public
//  @Author Maikel Zweerink
//  app.js - AngularJS application
// -----------------------------------------------------------------------

angular.module('whatsspy', ['ngRoute', 'ngVis', 'whatsspyFilters', 'whatsspyControllers', 'angularMoment', 'nvd3ChartDirectives', 'ui.multiselect'])
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
  .when('/public/group/:token', {
    templateUrl: 'statistics.html',
    controller: 'StatisticsController'
  })
  .when('/public/user/:token', {
    templateUrl: 'overview.html',
    controller: 'OverviewController'
  })
  .when('/login', {
    templateUrl: 'login.html',
    controller: 'LoginController'
  })
  .otherwise({redirectTo: '/overview'});;
})
.controller('MainController', function($scope, $rootScope, $location, $http, $q, $filter, $sce) {
  // Version of the application
  $rootScope.version = '1.5.6';

  $('[data-toggle="tooltip"]').tooltip();

  // Set active buttons according to the current page
  $scope.getActivePageClass = function(path) {
    if ($location.path().substr(1, path.length) == path) {
      return "menu-button active-option";
    } else {
      return "menu-button";
    }
  }

  $scope.isInPage = function(path) {
    if ($location.path().substr(1, path.length) == path) {
      return true;
    } else {
      return false;
    }
  }

  $rootScope.timelineLengthOptions = [{name: '24 hours (best performance)', value: 1},
                                      {name: '7  days', value: 7},
                                      {name: '14 days', value: 14},
                                      {name: '31 days (slow)', value: 31},
                                      {name: '90 days (very slow)', value: 90}];

  $rootScope.constructor = function() {
    // This data is required for this whole Angularjs Application
    $rootScope.accounts = [];
    $rootScope.pendingAccounts = [];
    $rootScope.groups = null;
    $rootScope.profilePicPath = null;
    $rootScope.notificationSettings = null;
    $rootScope.trackerStart = null;
    $rootScope.loadedTime = null;
    $rootScope.newestVersion = null;
    $rootScope.timelineLength = 14;
    $rootScope.help = null;
    $rootScope.news = null;
    $rootScope.headline = null;
    $rootScope.config = null;
    $rootScope.advancedControls = null;
    $rootScope.authenticated = false;
    $rootScope.aboutNotifications = 0;
    // Information that might be lazy loaded.
    $rootScope.accountData = {};

    // Tracker online/offline info (array).
    $rootScope.tracker = null;

    // Just some feedback when setting up WhatsSpy
    $rootScope.error = false;
  }

  $rootScope.constructor();

  $rootScope.getAccounts = function() {
    var deferred = $q.defer();
    $http({method: 'GET', url: 'api/?whatsspy=getStats'}).
      success(function(data, status, headers, config) {
        if(typeof data == 'string') {
          alertify.error('An error occured, please check your configuration:' + data);
          $rootScope.error = true;
        } else {
          if(data.error != null) {
            if(data.code == 403) {
              // Not logged in
              if($location.path().indexOf('/public') !== 0) {
                $location.path('/login');
              }
              $rootScope.tracker = null;
              $rootScope.accounts = [];
              $rootScope.pendingAccounts = [];
              $rootScope.groups = null;
            } else {
              alertify.error(data.error);
            }
          } else {
            $rootScope.authenticated = true;
            $rootScope.accounts = data.accounts;
            $rootScope.pendingAccounts = data.pendingAccounts;
            $rootScope.groups = data.groups;
            $rootScope.config = data.config;
            $rootScope.advancedControls = data.advancedControls;
            $rootScope.tracker = data.tracker;
            $rootScope.trackerStart = data.trackerStart;
            $rootScope.profilePicPath = data.profilePicPath;
            $rootScope.notificationSettings = data.notificationSettings;
            $rootScope.setNotificationOptions(data.notificationSettings);
            $rootScope.loadedTime = moment();
          }
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

  $rootScope.getAccountById = function(id) {
    for (var i = $rootScope.accounts.length - 1; i >= 0; i--) {
      if($rootScope.accounts[i].id == id) {
        return $rootScope.accounts[i];
        break;
      }
    };
    return null;
  }

  $rootScope.getAbout = function() {
  var deferred = $q.defer();
  $http({method: 'GET', url: 'api/?whatsspy=getAbout&v='+ $rootScope.version}).
    success(function(data, status, headers, config) {
      $rootScope.newestVersion = data.version;
      if($rootScope.version != $rootScope.newestVersion) {
        $rootScope.aboutNotifications = 1;
      }
      $rootScope.help = data.help;
      for (var i = $rootScope.help.length - 1; i >= 0; i--) {
        $rootScope.help[i].awnser = $sce.trustAsHtml($rootScope.help[i].awnser);
      };
      $rootScope.news = data.news;
      $rootScope.headline = $sce.trustAsHtml(data.headline);
      deferred.resolve(null);
    }).
    error(function(data, status, headers, config) {
      deferred.reject(null);
    });
  return deferred.promise;
  }

  $rootScope.trackerStatus = function() {
    if($rootScope.tracker != null && ($rootScope.tracker.length == 0 || $rootScope.tracker[0].end != null)) {
      return 'offline';
    } else if($rootScope.tracker != null && ($rootScope.tracker.length > 0 && $rootScope.tracker[0].end == null)) {
      return 'online';
    } else {
      return 'unknown';
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
    var query = '';
    if($rootScope.tokenAuth != null) {
      query = '&token='+$rootScope.tokenAuth;
    }
    var deferred = $q.defer();
    $http({method: 'GET', url: 'api/?whatsspy=getContactStats&number='+$number.id+query}).
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
          if($rootScope.accountData[$number.id] == undefined) {
            $rootScope.accountData[$number.id] = {};
          }
          $rootScope.accountData[$number.id].id = data[0].id;
          $rootScope.accountData[$number.id].user = data[0].user;
          $rootScope.accountData[$number.id].status = data[0].status;
          $rootScope.accountData[$number.id].status_length = data[0].status_length;
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
        }
        deferred.resolve(null);
      }).
      error(function(data, status, headers, config) {
        deferred.reject(null);
      });
    return deferred.promise;
  }

  $rootScope.getImageURL = function(hash) {
    var url = 'api/?whatsspy=getProfilePic&hash=' + hash;
    if($rootScope.tokenAuth != null) {
      url += '&token='+$rootScope.tokenAuth;
    }
    return url;
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

  $rootScope.setNotificationOptions = function(settings) {
    var disabled = true;
    if(settings == null) {
      $rootScope.notificationOptionDisabled = true;
    } else {
      for (var agent in settings) {
          if (settings.hasOwnProperty(agent)) {
            if(settings[agent].enabled == true) {
              disabled = false;
              break;
            }
          }
      }
      $rootScope.notificationOptionDisabled = disabled;
    }
  }

  $rootScope.getOpposite = function(bool) {
    if(bool == true) {
      return false;
    } else {
      return true;
    }
  }

  $rootScope.generateReadOnlyToken = function(type, user, group) {
    var query = '';
    if(type == 'user') {
      query = 'number=' + user.id;
    } else if(type == 'group') {
      query = 'group=' + group.gid;
    }

    $http({method: 'GET', url: 'api/?whatsspy=generateToken&type=read_only&' + query}).
      success(function(data, status, headers, config) {
        if(data.success == true) {
          if(type == 'user') {
            user.read_only_token = data.token;
            $rootScope.getAccountById(user.id).read_only_token  = data.token;
          } else if(type == 'group') {
            group.read_only_token = data.token;
          }
        } else {
          alertify.error(data.error);
        }
      }).
      error(function(data, status, headers, config) {
        alertify.error("Could not contact the server.");
      });
  }

  $rootScope.resetReadOnlyToken = function(type, user, group) {
    var query = '';
    if(type == 'user') {
      query = 'number=' + user.id;
    } else if(type == 'group') {
      query = 'group=' + group.gid;
    }

    $http({method: 'GET', url: 'api/?whatsspy=resetToken&type=read_only&' + query}).
      success(function(data, status, headers, config) {
        if(data.success == true) {
          if(type == 'user') {
            user.read_only_token = null;
            $rootScope.getAccountById(user.id).read_only_token  = null;
          } else if(type == 'group') {
            group.read_only_token = null;
          }
        } else {
          alertify.error(data.error);
        }
      }).
      error(function(data, status, headers, config) {
        alertify.error("Could not contact the server.");
      });
  }

  $rootScope.getTokenUrl = function(type, token) {
    if(token == null) {
      return null;
    } else {
      if(type == 'user') {
        return document.URL.split('#')[0] + '#/public/user/' + token;
      } else if(type == 'group') {
        return document.URL.split('#')[0] + '#/public/group/' + token;
      }
    }
  }

  $rootScope.copyToClipboard = function(text) {
    if(text != null) {
      window.prompt("Copy to clipboard: Ctrl+C, Enter", text);
    }
  }

  $rootScope.getGroupName = function(gid) {
    for (var i = $rootScope.groups.length - 1; i >= 0; i--) {
      if($rootScope.groups[i]['gid'] == gid) {
        return $rootScope.groups[i]['name'];
      }
    };
  }

  $rootScope.getGroupById = function(gid) {
    if($rootScope.groups == null) {return;}
    for (var i = $rootScope.groups.length - 1; i >= 0; i--) {
      if($rootScope.groups[i]['gid'] == gid) {
        return $rootScope.groups[i];
      }
    };
  }

  $rootScope.clone = function(obj) {
      var copy;

      // Handle the 3 simple types, and null or undefined
      if (null == obj || "object" != typeof obj) return obj;

      // Handle Date
      if (obj instanceof Date) {
          copy = new Date();
          copy.setTime(obj.getTime());
          return copy;
      }

      // Handle Array
      if (obj instanceof Array) {
          copy = [];
          for (var i = 0, len = obj.length; i < len; i++) {
              copy[i] = $rootScope.clone(obj[i]);
          }
          return copy;
      }

      // Handle Object
      if (obj instanceof Object) {
          copy = {};
          for (var attr in obj) {
              if (obj.hasOwnProperty(attr)) copy[attr] = $rootScope.clone(obj[attr]);
          }
          return copy;
      }

      throw new Error("Unable to copy obj! Its type isn't supported.");
  }

  $rootScope.doLogout = function(updateUI) {
    $http({method: 'GET', url: 'api/?whatsspy=doLogout'}).
      success(function(data, status, headers, config) {
        if(data.success == true) {
          if(updateUI == true) {
            $rootScope.authenticated = false;
            $rootScope.refreshContent();
          }
        } else {
          alertify.error(data.error);
        }
      }).
      error(function(data, status, headers, config) {
        alertify.error("Could not contact the server.");
      });
  }


  // Get all the required information
  // @variable slack set this to true to only fetch the global information and not new timelines etc.
  $rootScope.refreshContent = function(slack) {
    slack = typeof slack !== 'undefined' ? slack : false;
    $rootScope.showLoader = true;
    var promises = [];
    promises[0] = $rootScope.getAccounts();

    if($rootScope.help == null) {
      promises[1] = $rootScope.getAbout();
    }

    if($rootScope.inStatsPage == true) {
      promises[2] = $rootScope.loadGlobalStats('global_stats');
      promises[3] = $rootScope.loadGlobalStats('user_status_analytics_user');
      promises[4] = $rootScope.loadGlobalStats('user_status_analytics_time');
      promises[5] = $rootScope.loadGlobalStats('top_usage_users');
    }
    // Load any new status
    if(Object.keys($rootScope.accountData).length > 0 && slack == false) {
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
  if(!$scope.isInPage('public')) {
    $scope.refreshContent();
  } else {
    $rootScope.authenticated = false;
  }

});