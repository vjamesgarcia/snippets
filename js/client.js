/* client.js - client entry point for the iso react */
import 'babel/polyfill';

import React from 'react';
import ReactDOM from 'react-dom';
import createStore from './redux/create';
import {Provider} from 'react-redux';
import {reduxReactRouter, ReduxRouter} from 'redux-router';
import getRoutes from './routes';

import createHistory from 'history/lib/createBrowserHistory';
const client = new ApiClient();

const attach = document.getElementById('attach');
const store = createStore(reduxReactRouter, makeRouteHooksSafe(getRoutes), client, window.__data__);

const main_comp = (
  <ReduxRouter routes={getRoutes(store)} />
);

ReactDOM.render(
  <Provider store={store} key="provider">
    {main_comp}
  </Provider>,
  attach
);

if (process.env.NODE_ENV !== 'production') {
  window.React = React; // enable debugger

  if (!attach || !attach.firstChild || !attach.firstChild.attributes || !attach.firstChild.attributes['data-react-checksum']) {
    console.error('Not attached correctly');
  }
}

if (__DEVTOOLS__ && !window.devToolsExtension) {
  const DevTools = require('./containers/DevTools/DevTools');
  ReactDOM.render(
    <Provider store={store} key="provider">
      <div>
        {main_comp}
        <DevTools />
      </div>
    </Provider>,
    attach
  );
}
