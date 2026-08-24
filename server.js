require('dotenv').config();
const express = require('express');
const nodemailer = require('nodemailer');
const cors = require('cors');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 5000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname)));

app.post('/api/contact', async (req, res) => {
    const { fullName, companyName, email, phone, requirement } = req.body;

    if (!fullName || !companyName || !email || !phone || !requirement) {
        return res.status(400).json({
            success: false,
            message: 'All fields are required.'
        });
    }

    const senderEmail = (process.env.GODADDY_EMAIL || '').trim();
    const senderPassword = (process.env.GODADDY_PASSWORD || '').trim();
    const recipientEmail = (process.env.RECIPIENT_EMAIL || senderEmail).trim();

    if (!senderEmail || !senderPassword) {
        return res.status(400).json({
            success: false,
            message: 'Email credentials missing in .env file. Please enter GODADDY_EMAIL and GODADDY_PASSWORD.'
        });
    }

    let transporterConfig;

    // Detect if user provided a Gmail address vs GoDaddy Custom Domain Email
    if (senderEmail.toLowerCase().endsWith('@gmail.com')) {
        console.log('Detected Gmail address. Configuring Gmail SMTP (smtp.gmail.com)...');
        transporterConfig = {
            service: 'gmail',
            auth: {
                user: senderEmail,
                pass: senderPassword
            }
        };
    } else {
        const smtpHost = process.env.SMTP_HOST || 'smtpout.secureserver.net';
        const smtpPort = parseInt(process.env.SMTP_PORT || '465', 10);
        const isSecure = process.env.SMTP_SECURE === 'true' || smtpPort === 465;

        console.log(`Configuring GoDaddy SMTP (${smtpHost}:${smtpPort}) for ${senderEmail}...`);
        transporterConfig = {
            host: smtpHost,
            port: smtpPort,
            secure: isSecure,
            auth: {
                user: senderEmail,
                pass: senderPassword
            },
            tls: {
                rejectUnauthorized: false
            }
        };
    }

    const transporter = nodemailer.createTransport(transporterConfig);

    const mailOptions = {
        from: `"Gigzar Inquiry" <${senderEmail}>`,
        to: recipientEmail,
        replyTo: email,
        subject: `New Manpower Inquiry from ${fullName} (${companyName})`,
        html: `
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                <div style="background-color: #2563eb; color: #ffffff; padding: 20px; text-align: center;">
                    <h2 style="margin: 0; font-size: 24px;">Gigzar - New Callback Request</h2>
                </div>
                <div style="padding: 24px; color: #333333; line-height: 1.6;">
                    <p style="font-size: 16px; margin-top: 0;">You have received a new manpower callback request from your website form:</p>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee; font-weight: bold; width: 140px;">Full Name:</td>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee;">${fullName}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee; font-weight: bold;">Company Name:</td>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee;">${companyName}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee; font-weight: bold;">Email Address:</td>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee;"><a href="mailto:${email}">${email}</a></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee; font-weight: bold;">Phone Number:</td>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee;"><a href="tel:${phone}">${phone}</a></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee; font-weight: bold; vertical-align: top;">Requirement:</td>
                            <td style="padding: 10px; border-bottom: 1px solid #eeeeee;">${requirement.replace(/\n/g, '<br>')}</td>
                        </tr>
                    </table>
                </div>
                <div style="background-color: #f8fafc; color: #64748b; padding: 12px 20px; font-size: 12px; text-align: center; border-top: 1px solid #e0e0e0;">
                    Received on ${new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' })}
                </div>
            </div>
        `
    };

    try {
        const info = await transporter.sendMail(mailOptions);
        console.log('Email sent successfully:', info.messageId);
        return res.status(200).json({
            success: true,
            message: 'Your request has been sent successfully! Our team will contact you within 24 hours.'
        });
    } catch (error) {
        console.error('Error sending email:', error);
        
        let customHelpMsg = 'Email sending failed. Please check your credentials.';
        if (senderEmail.endsWith('@gmail.com')) {
            customHelpMsg = 'For @gmail.com, Google requires a 16-character "App Password" (not your normal Gmail password). Generate it at myaccount.google.com/apppasswords';
        } else {
            customHelpMsg = 'For GoDaddy domain email (e.g. admin@gigzar.com), ensure you enter your GoDaddy domain email & password.';
        }

        return res.status(500).json({
            success: false,
            message: customHelpMsg,
            error: error.message
        });
    }
});

app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

app.listen(PORT, () => {
    console.log(`=================================================`);
    console.log(`Gigzar Backend Server running on http://localhost:${PORT}`);
    console.log(`=================================================`);
});
