<!DOCTYPE html>
<html ng-app="MyApp">
<head>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
  <style>
    .list-group-item.expanded {
      color: #777;
      background-color: #eee;
    }
    
    table.diff, td.diff-otitle, td.diff-ntitle {
      background-color: white;
    }
    td.diff-otitle, td.diff-ntitle {
      text-align: center;
    }
    td.diff-marker {
      font-size: 1.25em;
      font-weight: bold;
      text-align: right;
    }
    td.diff-lineno {
      font-weight: bold;
    }
    td.diff-addedline, td.diff-deletedline, td.diff-context {
      font-size: 88%;
      vertical-align: top;
      white-space: pre-wrap;
    }
    td.diff-addedline, td.diff-deletedline {
      border-radius: 0.33em 0.33em 0.33em 0.33em;
      border-style: solid;
      border-width: 1px 1px 1px 4px;
    }
    td.diff-addedline {
      border-color: #A3D3FF;
    }
    td.diff-deletedline {
      border-color: #FFE49C;
    }
    td.diff-context {
      background: none repeat scroll 0 0 #F3F3F3;
      border-color: #E6E6E6;
      border-radius: 0.33em 0.33em 0.33em 0.33em;
      border-style: solid;
      border-width: 1px 1px 1px 4px;
      color: #333333;
    }
    .diffchange {
      font-weight: bold;
      text-decoration: none;
    }
    table.diff {
      border: medium none;
      border-spacing: 4px;
      table-layout: fixed;
      width: 98%;
    }
    td.diff-addedline .diffchange, td.diff-deletedline .diffchange {
      border-radius: 0.33em 0.33em 0.33em 0.33em;
      padding: 0.25em 0;
    }
    td.diff-addedline .diffchange {
      background: none repeat scroll 0 0 #D8ECFF;
    }
    td.diff-deletedline .diffchange {
      background: none repeat scroll 0 0 #FEEEC8;
    }
    table.diff td {
      padding: 0.33em 0.66em;
    }
    table.diff col.diff-marker {
      width: 2%;
    }
    table.diff col.diff-content {
      width: 48%;
    }
    table.diff td div {
      overflow: auto;
      word-wrap: break-word;
    }
  </style>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/underscore.js/1.7.0/underscore-min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.3.12/angular.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.3.12/angular-sanitize.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-timeago/1.4.1/jquery.timeago.min.js"></script>
  <script>
    var app = angular.module('MyApp', ['ngSanitize']);

    app.controller('Main', function($scope, $http){
      $http({
        url: 'http://gnuheter.com/creeper/jsonip'
      })
      .success(function(data){
        $scope.organisations = data;
      });
    });
    
    app.directive('load', function($http){
      return {
        link: function(scope, element, attributes){
          scope = scope.$parent.$parent;
          
          element.click(function(event){
            event.preventDefault();
            
            scope.$apply(function(){ scope.loading = true; });
            element.attr('disabled', true);
            
            $http({
              url: 'http://tools.wmflabs.org/wikiwatchdog/watchdog.py?callback=JSON_CALLBACK&domain=' + scope.organisation.min + '&lang=sv',
              method: 'jsonp'
            })
            .success(function(data){
              scope.data = data;
              scope.loading = false;
              scope.organisation.expanded = true;
            });
          });
        }
      };
    });
    
    app.directive('ngClick', function(){
      return {
        link: function(scope, element, attributes){
          element.click(function(event){
            event.preventDefault();
          });
        }
      };
    });
    
    app.filter('timeago', function(){
      return function(edit){
        if(!edit.parsedTimestamp){
          edit.parsedTimestamp = parseTimestamp(edit.timestamp);
        }
        
        return $.timeago(edit.parsedTimestamp);
      }
    });
    
    function parseTimestamp(i){
      return Date.parse(i.substr(0, 4) + '-' + i.substr(4,2) + '-' + i.substr(6, 2) + ' ' + i.substr(8, 2) + ':' + i.substr(10, 2)) + 3600000 /*1hr in milliseconds, GMT+1...*/;
    }
    
    app.directive('expandPage', function($http){
      return {
        link: function(scope, element, attributes){
          element.click(function(event){
            event.preventDefault();
            
            scope.$apply(function(){
              scope.page.expanded = !scope.page.expanded;
            });
            
            if(scope.page.expanded && !scope.page.currentEdit){
              scope.$apply(function(){
                showEdit(scope.page, scope.page.edits[0].id, $http);
              });
            }
            
            element.closest('.list-group-item').toggleClass('expanded', scope.page.expanded);
          });
        }
      };
    });
    
    app.directive('showEdit', function($http){
      return {
        link: function(scope, element, attributes){
          element.click(function(event){
            event.preventDefault();
            
            scope.$apply(function(){
              showEdit(scope.page, scope.edit.id, $http);
            });
          });
        }
      };
    });
    
    function showEdit(page, editId, $http){
      page.loading = editId;
      page.currentEditId = editId;
      
      $http({
        url: 'http://sv.wikipedia.org/w/api.php?callback=JSON_CALLBACK&action=query&prop=revisions&revids=' + editId + '&rvdiffto=prev&format=json',
        method: 'jsonp'
      })
      .success(function(data){
        page.currentEdit = data;
        page.loading = false;
      });
    }
    
    app.filter('lastEdit', function(){
      return lastEditOfPage;
    });
    
    function lastEditOfPage(page){
        var lastEdit = null;
        
        _.each(page.edits, function(edit){
          if(!edit.parsedTimestamp){
            edit.parsedTimestamp = parseTimestamp(edit.timestamp);
          }
          
          if(!lastEdit){
            lastEdit = edit;
            return;
          }
          
          if(edit.parsedTimestamp > lastEdit.parsedTimestamp){
            lastEdit = edit;
          }
        });
        
        return lastEdit;
      }

    app.filter('pagesWithMostRecentEditFirst', function(){
      return function(pages){
        return _.sortBy(pages, function(page){
          var lastEdit = lastEditOfPage(page);
          
          return -lastEdit.parsedTimestamp;
        });
      };
    });
    
    app.directive('tweet', function(){
      return {
        link: function(scope, element, attributes){
          element.attr('href', 'http://twitter.com/share?text=Anonyma+Wikipedia-redigeringar+av+svenska+myndigheter+och+organisationer&url=' + encodeURIComponent(location.href) + '&hashtags=creeper');
        }
      }
    });
  </script>
</head>
<body ng-controller="Main">
  <i class="fa fa-refresh fa-spin" style="position: absolute; left: -999em;"></i>
  <div class="container">
    <div class="row">
      <div class="col-sm-3"></div>
      <div class="col-sm-6">
        <div style="margin: 0 20px;">
          <h1>Anonyma Wikipedia-redigeringar <small>grupperade efter IP-nummer</small></h1>
          <p>Använder IP-block från <a href="http://gnuheter.com/creeper/">Creeper</a>, och använder <a href="http://tools.wmflabs.org/">Wikimedia Tool Labs API</a> för att hämta anonyma redigeringar.</p>
          <p>Visste du t.ex. att Socialstyrelsen har censurerat en artikel om PKK-Spåret (Palmemordet)?</p>
          <p>Glöm inte att <a tweet>twittra</a> om detta.</p>
        </div>
      </div>
      <div class="col-sm-3"></div>
    </div>
    <div class="panel panel-default" ng-repeat="organisation in organisations">
      <div class="panel-heading">
        {{ organisation.name }}
        <span ng-if="!data">
          <a href="#" class="btn btn-default btn-primary btn-xs" ng-if="!data" load>Load</a>
          <span ng-if="loading"><i class="fa fa-refresh fa-spin"></i></span>
        </span>
        <span ng-if="data">
          <a href="#" class="btn btn-default btn-primary btn-xs" ng-click="organisation.expanded = !organisation.expanded" ng-disabled="data.stats.pages == 0">
            <span ng-if="!organisation.expanded">Expand</span>
            <span ng-if="organisation.expanded">Close</span>
          </a>
          <span ng-if="data.stats.hasOwnProperty('pages')">{{ data.stats.pages }} pages</span>
        </span>
      </div>
      <div class="list-group" ng-if="organisation.expanded">
        <div class="list-group-item" ng-repeat-start="page in data.pages | pagesWithMostRecentEditFirst">
          <a href="http://sv.wikipedia.org/?curid={{ page.id }}" target="_blank">{{ page.title }}</a>
          ({{page.edits.length}} edits, last one {{ page | lastEdit | timeago }})
          <a href="#" class="btn btn-default btn-xs btn-primary" expand-page>
            <span ng-if="!page.expanded">Expand</span>
            <span ng-if="page.expanded">Close</span>
          </a>
          <span ng-if="page.loading"><i class="fa fa-refresh fa-spin"></i></span>
        </div>
        <div class="list-group-item" ng-if="page.expanded" ng-repeat-end>
          <div class="row">
            <div class="col-sm-3">
              <div class="list-group">
                <a href="http://sv.wikipedia.org/w/index.php?diff={{ edit.id }}" target="_blank" class="list-group-item" ng-class="{active: page.currentEditId == edit.id}" ng-repeat="edit in page.edits" show-edit>
                  {{ edit | timeago }}
                  <code ng-if="edit.comment">({{ edit.comment }})</code>
                  <span ng-if="page.loading && page.currentEditId == edit.id"><i class="fa fa-refresh fa-spin"></i></span>
                </a>
              </div>
            </div>
            <div class="col-sm-9">
              <table class="diff" ng-if="page.currentEdit">
                <colgroup>
                  <col class="diff-marker">
                  <col class="diff-content">
                  <col class="diff-marker">
                  <col class="diff-content">
                </colgroup>
                <tbody ng-bind-html="page.currentEdit.query.pages[page.id].revisions[0].diff['*']"></tbody>
              </table> 
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
