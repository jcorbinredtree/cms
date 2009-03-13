function ModalBox(content, width, height) {
  if (typeof width == undefined || width == null)
    width = '85%';
  if (typeof height == undefined || height == null)
    height = '75%';

  this.wantedSize = [width, height];

  this.overlay = document.createElement('div');
  this.overlay.className = 'ModalBoxOverlay';
  this.overlay.style.display = 'block';
  this.overlay.style.position = 'absolute';
  this.overlay.style.top = '0px';
  this.overlay.style.left = '0px';
  this.overlay.style.zIndex = '90';

  this.box = document.createElement('div');
  this.box.className = 'ModalBox';
  this.box.style.display = 'block';
  this.box.style.position = 'absolute';
  this.box.style.height = '20px';
  this.box.style.width = '20px';
  this.box.style.top = '0px';
  this.box.style.left = '0px';
  this.box.style.zIndex = '100';

  RedTree.Util.listen(
    this.box.appendChild(
      ModalBox.CreateCloseButton(document)
    ), 'click',
    RedTree.Util.bind(this, function(e) {
      this.close();
      return RedTree.Util.stopEvent(e);
    })
  );
  this.box.lastChild.style.zIndex = '110';

  if (document.body.firstChild) {
    document.body.insertBefore(
      this.overlay, document.body.firstChild
    );
    document.body.insertBefore(
      this.box, document.body.firstChild
    );
  } else {
    document.body.appendChild(this.overlay);
    document.body.appendChild(this.box);
  }

  this.resize();
  this.resizeListener = RedTree.Util.listen(
    window, 'resize',
    RedTree.Util.bind(this, this.resize)
  );

  if (typeof content == 'function') {
    content(this.box)
  } else if (typeof content == 'string') {
    var frame = RedTree.Util.createIframe(this.box);
    frame.frameBorder = 0;
    frame.width = '100%';
    frame.height = '100%';
    if (content.indexOf("\n") == -1 && /^\w+:\/\//.test(content)) {
      frame.src = content;
    } else {
      frame.doc.open();
      frame.doc.write(content);
      frame.doc.close();
    }
  }
}
ModalBox.ParseSize = function (s, avail) {
  if (typeof s == 'number') {
    return s;
  } else if (typeof s == 'string') {
    var i = parseInt(s);
    if (isNaN(i)) {
      throw new Error('invalid size');
    }
    if (s.substr(-1) == '%') {
      return Math.floor(avail * i / 100);
    } else {
      return i;
    }
  } else {
    throw new Error('invalid size');
  }
};
ModalBox.CreateCloseButton = function (doc) {
  var bClose = doc.createElement('a');
  bClose.className = 'ModalBoxClose';
  bClose.href = '#';
  bClose.title = 'Close';
  bClose.appendChild(doc.createTextNode('X'));
  return bClose;
};
ModalBox.prototype = {
  onclose: null,

  getFrame: function () {
    return this.box.getElementsByTagName('iframe')[0];
  },
  resize: function() {
    var vp = [
      Math.max(
        document.documentElement.clientWidth,
        document.documentElement.scrollWidth
      ),
      Math.max(
        document.documentElement.clientHeight,
        document.documentElement.scrollHeight
      )
    ];
    this.overlay.style.width  = vp[0] + 'px';
    this.overlay.style.height = vp[1] + 'px';

    var boxDelta = [ // CSS box model difference between style and offset
      this.box.offsetWidth  - parseInt(this.box.style.width),
      this.box.offsetHeight - parseInt(this.box.style.height)
    ];

    var wantedSize = [
      ModalBox.ParseSize(this.wantedSize[0], window.innerWidth),
      ModalBox.ParseSize(this.wantedSize[1], window.innerHeight)
    ];

    var boxSize = [ // box size or 95% of viewport
      Math.min(Math.floor(vp[0]*0.95), wantedSize[0]) + boxDelta[0],
      Math.min(Math.floor(vp[1]*0.95), wantedSize[1]) + boxDelta[1]
    ];

    this.box.style.width  = boxSize[0]+'px';
    this.box.style.height = boxSize[1]+'px';

    this.box.style.left = Math.floor(
      Math.max(0, (window.innerWidth - boxSize[0])/2)
    )+'px';
    this.box.style.top  = Math.floor(
      Math.max(0, (window.innerHeight - boxSize[1])/2)
    )+'px';
  },
  close: function () {
    if (this.onclose)
      this.onclose(this);
    RedTree.Util.unlisten(window, 'resize', this.resizeListener);
    this.overlay.parentNode.removeChild(this.overlay);
    this.box.parentNode.removeChild(this.box);
    delete this.resizeListener;
    delete this.overlay;
    delete this.box;
  }
};
