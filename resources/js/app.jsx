import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import RuteAplikasi from './routes';
import '../css/app.css';

createRoot(document.getElementById('app')).render(
    <StrictMode>
        <BrowserRouter>
            <AuthProvider>
                <RuteAplikasi />
            </AuthProvider>
        </BrowserRouter>
    </StrictMode>,
);
