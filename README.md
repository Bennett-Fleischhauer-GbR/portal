# Portal

**Portal** is a web application designed to efficiently manage quick links and files in various categories. It offers a user-friendly interface, multi-language support, and seamless integration with a YOURLS instance via API for URL shortening. Additionally, the app includes a user management system with role-based access control.

## Features

### 1. **Quick Link Management**
- Store and organize quick links in different categories for easy and fast access.
- Supports URL shortening using the YOURLS API.

### 2. **File Management**
- Upload and manage files, such as PDFs or other resources, within different categories.
- Automatic favicon fetching for URLs, with fallback options.

### 3. **User Management**
- Role-based access control (Admin, Author, User).
- Ability to assign permissions for managing categories and content.
  
### 4. **Multi-language Support**
- Use the app in your preferred language (supports English, German, Spanish, Chinese, French, etc.).
- Language is automatically set based on user preferences.

### 5. **Dashboard**
- Display widgets for date, time, and weather.
- Weather data retrieval via OpenWeather API with customizable temperature units and language.

### 6. **Settings and Customization**
- Customize logos, colors, and favicon.
- Set a custom footer with dynamic options (e.g., displaying company foundation year or copyright).
- Email notifications via configurable SMTP settings.

## System Requirements

- **Webserver**: Apache or Nginx
- **PHP**: Version 8.0 or higher
- **Database**: MySQL 5.7 or higher
- **Other Dependencies**:
  - Bootstrap 5.x for the front-end
  - Font Awesome for icons
  - YOURLS API integration (optional)
  - OpenWeather API (optional)

## Installation

Follow these steps to install **Portal** on your server.

1. **Download the installer:**

   Download the content from the repository.

2. **Upload to your server:**

    Upload the content to your webserver's root directory.

3. **Run the installer:**

    Access the installer through your web browser by navigating to:
    http://yourdomain.com/install/

    Follow the installation steps to configure your database and set up the application.

4. **Configure YOURLS API (Optional):**

    If you want to use YOURLS for URL shortening, provide the YOURLS API URL and token in the admin settings.

5. **Set up OpenWeather API (Optional):**

    Obtain an API key from OpenWeather and add it to the admin settings to enable weather features.

## Contributing

We welcome contributions to improve Portal! If you find a bug or have a feature request, please open an issue or submit a pull request.

**Steps to Contribute:**

1. Fork the repository.
2. Create a new branch for your feature or bug fix.
3. Commit your changes and push them to your branch.
4. Open a pull request, explaining the changes.

## License

Portal is licensed under the **Free Usage License Version 1.0**, specifically created for the "Portal" repository of **Bennett & Fleischhauer GbR**.

### Terms of Use:

- The software can be freely used by anyone. However, if modifications are made, they must be shared with the community and the original developers.
- Modifications cannot be independently distributed or sold.
- Derived works must also adhere to the same terms.
- Selling the software is strictly prohibited. Companies are allowed to use the software but may not resell it or modified versions.
- Any modifications should be shared with the community for the benefit of all, allowing developers to make improvements if needed.
- No warranty or liability is provided. The software is provided "as-is".
- Future updates are not guaranteed but will be provided based on demand.
- Any new developers wishing to take over maintenance must first reach an agreement with the original developers.

## Support

For any questions or support, feel free to contact us:

- **Email**: portal@bennett-fleischhauer.de

We encourage open communication and collaboration with the community to improve **Portal**. Any contributions, ideas, or issues are greatly appreciated.

---

### Future Considerations

- At the moment, there are no restrictions based on geography or user type, but all uses of the software should be for the betterment of the community.
- We reserve the right to update the terms or modify the license in the future if necessary, especially if the project becomes more widely adopted.
- Updates will depend on demand and the needs of the community.

Thank you for using **Portal**!