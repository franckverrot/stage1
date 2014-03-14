var DomSelector = function($, callback) {
    var self = this;

    this.locked = false;
    this.enabled = false;
    this.target = document.getElementsByTagName('body')[0];
    this.container = document.getElementsByTagName('body')[0];
    this.$ = $;
    this.callback = callback.bind(this);

    this.mouseoverCallback = function(event) {
        self.calc(event.target);
        event.stopPropagation();
    };

    this.clickCallback = function(event) {
        if ($('#stage1-sidebar').find(event.target).length > 0) {
            return;
        }

        if (self.isOverlay(event.target)) {
            self.unlock();
            self.calc(event.target);
        } else {
            self.callback(event.target);
        }

        event.preventDefault();
    };

    this.keydownCallback = function(event) {
        if (event.keyCode === 27) {
            self.disable();
        }
    };

    this._createOverlay = function(id) {
        var overlay = document.createElement('div');
        overlay.id = 'DomSelector-overlay-' + id;

        self.$(overlay).css({
            'position': 'fixed',
            'z-index': 9999,
            'background-color': 'black',
            'opacity': 0.5,
            'point-events': 'none',
        });

        return overlay;
    }

    this.overlays = {
        top: this._createOverlay('top'),
        bottom: this._createOverlay('bottom'),
        left: this._createOverlay('left'),
        right: this._createOverlay('right')
    };

    for (i in this.overlays) {
        this.container.appendChild(this.overlays[i]);
    }

    $('[data-toggle=selector]')
        .css('cursor', 'pointer')
        .on('click', function(event) {
            self.toggle();
            self.calc(event.target);
            event.stopPropagation();
        });
};

DomSelector.prototype.isOverlay = function(element) {
    for (i in this.overlays) {
        if (element.id === this.overlays[i].id) {
            return true;
        }
    }

    return false;
};

DomSelector.prototype.toggle = function() {
    this.enabled ? this.disable() : this.enable();
};

DomSelector.prototype.toggleLock = function() {
    this.locked ? this.unlock() : this.lock();
}

DomSelector.prototype.enable = function () {
    for (i in this.overlays) {
        this.$(this.overlays[i]).show();
    }

    this.$(this.target)
        .css('cursor', 'pointer')
        .on('mouseover', this.mouseoverCallback)
        .on('click', this.clickCallback)
        .on('keydown', this.keydownCallback);

    this.enabled = true;
}

DomSelector.prototype.disable = function () {
    for (i in this.overlays) {
        this.$(this.overlays[i]).hide();
    }

    this.$(this.target)
        .css('cursor', '')
        .off('mouseover', this.mouseoverCallback)
        .off('click', this.clickCallback)
        .off('keydown', this.keydownCallback);

    this.unlock();

    this.enabled = false;
}

DomSelector.prototype.lock = function() {
    this.locked = true;
};

DomSelector.prototype.unlock = function() {
    this.locked = false;
};

DomSelector.prototype.calc = function(element) {
    if (this.locked || !this.enabled) {
        return;
    }

    var rect = element.getBoundingClientRect();

    this.$(this.overlays.top).css({ top: 0, left: 0, width: '100%', height: rect.top });
    this.$(this.overlays.bottom).css({ top: rect.bottom, left: 0, height: '100%', width: '100%' });
    this.$(this.overlays.left).css({ left: 0, top: rect.top, height: rect.height, width: rect.left });
    this.$(this.overlays.right).css({ left: rect.right, top: rect.top, height: rect.height, width: '100%'});
};