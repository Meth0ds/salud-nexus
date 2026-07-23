import { booleanAttribute, Component, computed, input, numberAttribute } from '@angular/core';

@Component({
  selector: 'sn-icon',
  template: `
    <span
      class="material-symbols-rounded sn-icon__glyph"
      [attr.aria-hidden]="isDecorative() ? 'true' : null"
      [attr.aria-label]="accessibleLabel() || null"
      [attr.role]="isDecorative() ? null : 'img'"
      [style.--sn-icon-fill]="fillValue()"
      [style.--sn-icon-size]="sizeCss()"
      [style.--sn-icon-weight]="weight()"
      >{{ name() }}</span
    >
  `,
  styleUrl: './sn-icon.scss',
})
export class SnIcon {
  readonly name = input.required<string>();
  readonly accessibleLabel = input('');
  readonly fill = input(false, { transform: booleanAttribute });
  readonly size = input(20, { transform: numberAttribute });
  readonly weight = input(500, { transform: numberAttribute });

  protected readonly fillValue = computed(() => (this.fill() ? 1 : 0));
  protected readonly isDecorative = computed(() => this.accessibleLabel().trim().length === 0);
  protected readonly sizeCss = computed(() => `${Math.min(48, Math.max(12, this.size()))}px`);
}
