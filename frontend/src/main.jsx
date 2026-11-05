import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App.jsx';
import './index.css';
import { Provider } from 'react-redux';
import { store } from './app/store';
import { Toaster } from 'react-hot-toast';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <Provider store={store}>
      <App />
      <Toaster 
        position="top-center"
        toastOptions={{
          style: {
            background: '#f8e1e5',
            color: '#5c212c',
            border: '1px solid #823945',
            fontWeight: '600',
          },
        }}
      />
    </Provider>
  </React.StrictMode>,
);