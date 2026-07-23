import { Component, input } from '@angular/core';

import type { MotionProfile, ResponsiveProfile, VisualStateProfile } from '../../screen-catalog';

@Component({
  selector: 'sn-design-mockup-notes',
  templateUrl: './mockup-design-notes.html',
  styleUrl: './mockup-design-notes.scss',
})
export class MockupDesignNotes {
  readonly stateProfile = input<VisualStateProfile>();
  readonly responsiveProfile = input<ResponsiveProfile>();
  readonly motionProfile = input<MotionProfile>();
}
