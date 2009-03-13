function XMLHttpRequestDisplay(req) {
  var type = req.getResponseHeader('Content-Type');

  var mess =
    "Requested:\n" + req.method + ' ' + req.url + "\n" + req.sentData +
    "\nServer repsonded:\n"+ req.status + ' ' + req.statusText + "\n" +
    req.getAllResponseHeaders();

  var box;
  if (type.substr(0, 9) == 'text/html') {
    box = new ModalBox(req.responseText, '80%', '90%');
    var doc = box.getFrame().doc;
    var header = doc.body.insertBefore(
      doc.createElement('pre'), doc.body.firstChild
    );
    header.appendChild(doc.createTextNode(mess));
    header.style.borderBottom = '1px solid #999';
  } else {
    mess += "\n" + req.responseText;
    box = new ModalBox(function (box) {
      var doc = box.ownerDocument;
      var frame = box.appendChild(
        doc.createElement('div')
      );
      frame.style.height = '99%';
      frame.style.overflow = 'auto';
      frame.style.color = 'black';
      frame.style.backgroundColor = 'white';
      frame.appendChild(
        doc.createElement('pre')
      ).appendChild(
        doc.createTextNode(mess)
      );
    }, '80%', '90%');
  }
  box.onclose = function() {
    RedTree.Util.unlisten(window, 'keypress', this.keyListener);
    delete this.keyListener;
  };
  box.keyListener = RedTree.Util.listen(window, 'keypress',
    RedTree.Util.bind(box, function (e) {
      if (e == undefined) e = window.event;
      if (e.keyCode == 27) {
        this.close();
      }
    })
  );
}

RedTree.Util.addLoadEvent(function() {
  function trim(s) {
    return s.replace(/^\s+|\s+$/g, '');
  }

  var ConnectorRequest = (function() {
    function cls (type, action, extra, data, callback) {
      if (ConnectorRequest.ConnectorUrl == null)
        throw new Error('No ConnectorUrl set');

      var url = [ConnectorRequest.ConnectorUrl, type, action];
      if (extra != null)
        url.push(extra);
      url = url.join('/');
      this.callback = callback;

      var req = new XMLHttpRequest();
      req.onreadystatechange = RedTree.Util.bind(this, this.onReadyStateChange);
      req.url = url;
      req.method = 'POST';
      req.open(req.method, req.url, true);
      req.setRequestHeader('Content-Type', 'application/json');
      data = data == null ? '' : JSON.stringify(data);
      req.sentData = "Content-Type: application/json\n\n"+data;
      req.send(data);
    }
    cls.prototype = {
      onReadyStateChange: function(e) {
        var req = e.target;
        if (req.readyState != XMLHttpRequest.DONE)
          return;

        try {
          var type = req.getResponseHeader('Content-Type');

          if (type != 'application/json' || req.status != 200) {
            new XMLHttpRequestDisplay(req);
            return;
          }

          this.data = JSON.parse(req.responseText);
          this.callback(this.data);
        } catch (e) {
          if (ConnectorRequest.onError)
            ConnectorRequest.onError(e);
          else
            throw e;
        }
      }
    };
    return cls;
  })();

  var type   = document.getElementById('type');
  var action = document.getElementById('action');
  var extra  = document.getElementById('extra');
  var input  = document.getElementById('input');
  var output = document.getElementById('output');
  var submit = document.getElementById('submit');
  output.value = '';

  ConnectorRequest.ConnectorUrl =
    RedTree.Util.getLink('connector', 'application/json', 'CMS');

  if (ConnectorRequest.ConnectorUrl == null) {
    throw new Error('no connector url');
  }
  document.getElementById('connectorUrl').appendChild(
    document.createTextNode(ConnectorRequest.ConnectorUrl)
  );

  ConnectorRequest.onError = function (e) {
    output.value = "An error occured: "+e;
  };

  submit.onclick = function() {
    data = trim(input.value);
    if (data == '')
      data = null;
    else
      data = JSON.parse(data);
    var e = trim(extra.value);
    new ConnectorRequest(
      type.value,
      action.value,
      e == '' ? null : e,
      data,
      function (data) {
        output.value = JSON.stringify(data);
      }
    );
  };
});
