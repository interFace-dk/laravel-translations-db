@extends('layout.base')

@section('content')
<div class="container-fluid" ng-app="trans" ng-controller="Translations">
        <h1>{{ trans('translation::manager.title') }}</h1>
        <p>
            {{ trans('translation::manager.help') }}
        </p>

        <div ng-if="message" class="alert alert-[[ message.type ]]" role="alert">
            [[ message.text ]]
        </div>

        <div class="row" style="margin-bottom:10px">

            <div class="col-md-3">
                <select ng-model="currentGroup" ng-change="clear()" class="form-control">
                    <option ng-repeat="group in groups">[[ group ]]</option>
                </select>
            </div>

            <div class="col-md-4">
                <select ng-model="currentLocale" ng-change="clear()" class="form-control">
                    <option ng-repeat="locale in locales">[[ locale ]]</option>
                </select>
            </div>

            <div class="col-md-4">
                <input ng-change="clear()" class="form-control" maxlength="2" type="text" ng-model="currentEditable" placeholder="{{ trans('translation::manager.locale_placeholder') }}" />
            </div>

            <div class="col-md-1">
                <button class="btn btn-primary form-control" ng-click="fetch()">
                    {{ trans('translation::manager.button') }}
                </button>
            </div>
        </div>

        <div class="row" ng-if="items.length > 0">
            <div class="col-md-offset-2 col-md-6">
                <div class="progress">
                    <div class="progress-bar progress-bar-warning progress-bar-striped" style="width: [[ (translateResult.skip / translateResult.total) * 100 ]]%"></div>
                    <div class="progress-bar progress-bar-success progress-bar-striped" style="width: [[ (translateResult.success / translateResult.total) * 100 ]]%"></div>
                    <div class="progress-bar progress-bar-danger progress-bar-striped" style="width: [[ (translateResult.errors / translateResult.total) * 100 ]]%"></div>
                    <div class="progress-bar progress-bar-info progress-bar-striped" style="width: [[ (translateResult.loading / translateResult.total) * 100 ]]%"></div>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-info form-control" ng-click="translateAll()">
                    {{ trans('translation::manager.google') }}
                </button>
            </div>
        </div>

        <div class="row datarow" ng-repeat="item in items">
            <div class="col-md-3 text">
                [[ item.name ]]
                <span ng-if="item.check == true" class="label label-warning">Unsaved!</span>
            </div>
            <div class="col-md-3 text">
                [[ item.value ]]
            </div>
            <div class="col-md-5">
                <textarea class="form-control" ng-blur="store($index)" ng-model="item.translation" onfocus="jQuery(this).closest('.row').addClass('bg-success');" onblur="jQuery(this).closest('.row').removeClass('bg-success');"></textarea>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger" ng-click="delete($index)">
                    {{ trans('translation::manager.delete') }}
                </button>
            </div>
        </div>
    </div>
@endsection