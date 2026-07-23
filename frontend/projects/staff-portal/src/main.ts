import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { App } from './app/app';
import { renderBootstrapFailure } from './bootstrap-failure';

bootstrapApplication(App, appConfig).catch(() => renderBootstrapFailure(document));
