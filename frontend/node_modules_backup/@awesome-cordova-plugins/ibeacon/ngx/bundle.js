'use strict';

var tslib = require('tslib');
var i0 = require('@angular/core');
var core = require('@awesome-cordova-plugins/core');
var rxjs = require('rxjs');

function _interopNamespaceDefault(e) {
    var n = Object.create(null);
    if (e) {
        Object.keys(e).forEach(function (k) {
            if (k !== 'default') {
                var d = Object.getOwnPropertyDescriptor(e, k);
                Object.defineProperty(n, k, d.get ? d : {
                    enumerable: true,
                    get: function () { return e[k]; }
                });
            }
        });
    }
    n.default = e;
    return Object.freeze(n);
}

var i0__namespace = /*#__PURE__*/_interopNamespaceDefault(i0);

var IBeacon = /** @class */ (function (_super) {
    tslib.__extends(IBeacon, _super);
    function IBeacon() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    IBeacon.prototype.Delegate = function () {
        var _this = this;
        return (function () {
            if (core.checkAvailability(_this) === true) {
                var delegate = new window.cordova.plugins.locationManager.Delegate();
                delegate.didChangeAuthorizationStatus = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.didChangeAuthorizationStatus = cb);
                    });
                };
                delegate.didDetermineStateForRegion = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.didDetermineStateForRegion = cb);
                    });
                };
                delegate.didEnterRegion = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.didEnterRegion = cb);
                    });
                };
                delegate.didExitRegion = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.didExitRegion = cb);
                    });
                };
                delegate.didRangeBeaconsInRegion = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.didRangeBeaconsInRegion = cb);
                    });
                };
                delegate.didStartMonitoringForRegion = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.didStartMonitoringForRegion = cb);
                    });
                };
                delegate.monitoringDidFailForRegionWithError = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.monitoringDidFailForRegionWithError = cb);
                    });
                };
                delegate.peripheralManagerDidStartAdvertising = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.peripheralManagerDidStartAdvertising = cb);
                    });
                };
                delegate.peripheralManagerDidUpdateState = function (pluginResult) {
                    return new rxjs.Observable(function (observer) {
                        var cb = function (data) { return observer.next(data); };
                        return (delegate.peripheralManagerDidUpdateState = cb);
                    });
                };
                window.cordova.plugins.locationManager.setDelegate(delegate);
                return delegate;
            }
        })();
    };
    IBeacon.prototype.BeaconRegion = function (identifer, uuid, major, minor, notifyEntryStateOnDisplay) {
        var _this = this;
        return (function () {
            if (core.checkAvailability(_this) === true) {
                return new window.cordova.plugins.locationManager.BeaconRegion(identifer, uuid, major, minor, notifyEntryStateOnDisplay);
            }
        })();
    };
    IBeacon.prototype.getDelegate = function () { return core.cordova(this, "getDelegate", {}, arguments); };
    IBeacon.prototype.setDelegate = function (delegate) { return core.cordova(this, "setDelegate", {}, arguments); };
    IBeacon.prototype.onDomDelegateReady = function () { return core.cordova(this, "onDomDelegateReady", { "otherPromise": true }, arguments); };
    IBeacon.prototype.isBluetoothEnabled = function () { return core.cordova(this, "isBluetoothEnabled", { "otherPromise": true }, arguments); };
    IBeacon.prototype.enableBluetooth = function () { return core.cordova(this, "enableBluetooth", { "otherPromise": true }, arguments); };
    IBeacon.prototype.disableBluetooth = function () { return core.cordova(this, "disableBluetooth", { "otherPromise": true }, arguments); };
    IBeacon.prototype.startMonitoringForRegion = function (region) { return core.cordova(this, "startMonitoringForRegion", { "otherPromise": true }, arguments); };
    IBeacon.prototype.stopMonitoringForRegion = function (region) { return core.cordova(this, "stopMonitoringForRegion", { "otherPromise": true }, arguments); };
    IBeacon.prototype.requestStateForRegion = function (region) { return core.cordova(this, "requestStateForRegion", { "otherPromise": true }, arguments); };
    IBeacon.prototype.startRangingBeaconsInRegion = function (region) { return core.cordova(this, "startRangingBeaconsInRegion", { "otherPromise": true }, arguments); };
    IBeacon.prototype.stopRangingBeaconsInRegion = function (region) { return core.cordova(this, "stopRangingBeaconsInRegion", { "otherPromise": true }, arguments); };
    IBeacon.prototype.getAuthorizationStatus = function () { return core.cordova(this, "getAuthorizationStatus", { "otherPromise": true }, arguments); };
    IBeacon.prototype.requestWhenInUseAuthorization = function () { return core.cordova(this, "requestWhenInUseAuthorization", { "otherPromise": true }, arguments); };
    IBeacon.prototype.requestAlwaysAuthorization = function () { return core.cordova(this, "requestAlwaysAuthorization", { "otherPromise": true }, arguments); };
    IBeacon.prototype.getMonitoredRegions = function () { return core.cordova(this, "getMonitoredRegions", { "otherPromise": true }, arguments); };
    IBeacon.prototype.getRangedRegions = function () { return core.cordova(this, "getRangedRegions", { "otherPromise": true }, arguments); };
    IBeacon.prototype.isRangingAvailable = function () { return core.cordova(this, "isRangingAvailable", { "otherPromise": true }, arguments); };
    IBeacon.prototype.isMonitoringAvailableForClass = function (region) { return core.cordova(this, "isMonitoringAvailableForClass", { "otherPromise": true }, arguments); };
    IBeacon.prototype.startAdvertising = function (region, measuredPower) { return core.cordova(this, "startAdvertising", { "otherPromise": true }, arguments); };
    IBeacon.prototype.stopAdvertising = function (region) { return core.cordova(this, "stopAdvertising", { "otherPromise": true }, arguments); };
    IBeacon.prototype.isAdvertisingAvailable = function () { return core.cordova(this, "isAdvertisingAvailable", { "otherPromise": true }, arguments); };
    IBeacon.prototype.isAdvertising = function () { return core.cordova(this, "isAdvertising", { "otherPromise": true }, arguments); };
    IBeacon.prototype.disableDebugLogs = function () { return core.cordova(this, "disableDebugLogs", { "otherPromise": true }, arguments); };
    IBeacon.prototype.enableDebugNotifications = function () { return core.cordova(this, "enableDebugNotifications", { "otherPromise": true }, arguments); };
    IBeacon.prototype.disableDebugNotifications = function () { return core.cordova(this, "disableDebugNotifications", { "otherPromise": true }, arguments); };
    IBeacon.prototype.enableDebugLogs = function () { return core.cordova(this, "enableDebugLogs", { "otherPromise": true }, arguments); };
    IBeacon.prototype.appendToDeviceLog = function (message) { return core.cordova(this, "appendToDeviceLog", { "otherPromise": true }, arguments); };
    IBeacon.ɵfac = i0__namespace.ɵɵngDeclareFactory({ minVersion: "12.0.0", version: "12.2.17", ngImport: i0__namespace, type: IBeacon, deps: null, target: i0__namespace.ɵɵFactoryTarget.Injectable });
    IBeacon.ɵprov = i0__namespace.ɵɵngDeclareInjectable({ minVersion: "12.0.0", version: "12.2.17", ngImport: i0__namespace, type: IBeacon });
    IBeacon.pluginName = "IBeacon";
    IBeacon.plugin = "cordova-plugin-ibeacon";
    IBeacon.pluginRef = "cordova.plugins.locationManager";
    IBeacon.repo = "https://github.com/petermetz/cordova-plugin-ibeacon";
    IBeacon.platforms = ["Android", "iOS"];
    IBeacon = tslib.__decorate([], IBeacon);
    return IBeacon;
}(core.AwesomeCordovaNativePlugin));
i0__namespace.ɵɵngDeclareClassMetadata({ minVersion: "12.0.0", version: "12.2.17", ngImport: i0__namespace, type: IBeacon, decorators: [{
            type: i0.Injectable
        }], propDecorators: { Delegate: [], BeaconRegion: [], getDelegate: [], setDelegate: [], onDomDelegateReady: [], isBluetoothEnabled: [], enableBluetooth: [], disableBluetooth: [], startMonitoringForRegion: [], stopMonitoringForRegion: [], requestStateForRegion: [], startRangingBeaconsInRegion: [], stopRangingBeaconsInRegion: [], getAuthorizationStatus: [], requestWhenInUseAuthorization: [], requestAlwaysAuthorization: [], getMonitoredRegions: [], getRangedRegions: [], isRangingAvailable: [], isMonitoringAvailableForClass: [], startAdvertising: [], stopAdvertising: [], isAdvertisingAvailable: [], isAdvertising: [], disableDebugLogs: [], enableDebugNotifications: [], disableDebugNotifications: [], enableDebugLogs: [], appendToDeviceLog: [] } });

exports.IBeacon = IBeacon;
