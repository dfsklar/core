import Component from 'flarum/Component';
import Button from 'flarum/components/Button';

/**
 * The `WelcomeHero` component displays a hero that welcomes the user to the
 * forum.
 */
export default class WelcomeHero extends Component {
  init() {
    this.hidden = localStorage.getItem('welcomeHidden');
  }

  view() {
    if (this.hidden) return <div/>;

    const slideUp = () => {
      this.$().slideUp(this.hide.bind(this));
    };

    return (
      <header className="Hero WelcomeHero">
        <div class="container">
          <div className="containerNarrow">
            <h2 className="Hero-title">Looking for Formed.org Communities?</h2>
            <div className="Hero-subtitle">
              To access formed.org community groups, please login to formed.org and visit
              the home page for your parish or church organization.
            </div>
          </div>
        </div>
      </header>
    );
  }

  /**
   * Hide the welcome hero.
   */
  hide() {
    localStorage.setItem('welcomeHidden', 'true');

    this.hidden = true;
  }
}
