// import { startStimulusApp } from '@symfony/stimulus-bundle';
import { startStimulusApp } from '@symfony/stimulus-bridge';
import FlashController from './controllers/flash_controller.js';

const app = startStimulusApp();
app.register('flash', FlashController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
