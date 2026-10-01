<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BillingPlan;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Inserts default site content ONLY where it is missing - it never overwrites
 * anything the admin has edited, so it is safe to run on every deploy.
 * The legal pages are generic TEMPLATES: have them reviewed before going live.
 */
final class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SiteSetting::DEFAULTS as $name => $value) {
            SiteSetting::query()->firstOrCreate(['name' => $name], ['value' => $value, 'is_secret' => false]);
        }

        foreach ($this->pages() as $slug => [$meta, $body]) {
            SitePage::query()->firstOrCreate(
                ['slug' => $slug],
                ['title' => SitePage::SLUGS[$slug], 'body' => $body, 'meta_description' => $meta, 'is_published' => true],
            );
        }

        if (BillingPlan::query()->count() === 0) {
            $order = 0;
            foreach ((array) config('billing.plans') as $key => $plan) {
                BillingPlan::query()->create([
                    'key' => $key, 'name' => $plan['name'], 'amount_paise' => $plan['amount_paise'],
                    'days' => $plan['days'], 'is_active' => true, 'sort_order' => $order++,
                ]);
            }
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function pages(): array
    {
        return [
            'home' => ['Turn any website into approved, on-brand social media posts.', <<<'T'
# How it works
- Paste your website address and we read and understand your business.
- We suggest several creative ideas, with pros, cons and risks for each.
- You pick one, and we generate images, captions, hashtags and tracking links.
- You review and edit everything, then approve it.
- Approved posts are published to your connected Facebook Pages and Instagram accounts.

# Why teams use {{business_name}}
- Nothing is ever published without your explicit approval.
- Edit anything after approval and it must be approved again.
- Your website is analysed safely, and your connected accounts stay under your control.
T],
            'about-us' => ['About {{business_name}}', <<<'T'
# Who we are
{{business_name}} helps businesses turn their website into consistent, on-brand social media content without starting from a blank page.

# What we believe
- AI should speed up your work, not publish on your behalf without you knowing.
- You stay in control: every post is reviewed and approved by a person.
- Your data and your connected accounts deserve careful protection.
T],
            'services' => ['Services offered by {{business_name}}', <<<'T'
# What you get
- Website analysis: we extract your brand, offer and audience from your site.
- Creative concepts: multiple ideas with reasoning, pros, cons and risks.
- AI-generated images with detailed prompts you can edit.
- Captions, hashtags, calls to action and tracking links for each platform.
- Approval workflow with a full audit trail.
- Publishing to connected Facebook Pages and Instagram accounts, with status and analytics.
T],
            'contact-us' => ['Contact {{business_name}}', <<<'T'
Questions, feedback or need help with your account or a payment? Send us a message using the form below and we will respond as soon as we can.
T],
            'privacy-policy' => ['How {{business_name}} collects, uses and protects your information.', <<<'T'
# Privacy Policy
This policy explains how {{business_name}} ("we", "us") handles information when you use {{site_url}} and our services.

# Information we collect
- Account information: your name, email address and an encrypted password.
- Content you provide: website addresses you submit, text we extract from those pages, and the concepts, images, captions and posts generated in your workspace.
- Connected accounts: identifiers and access tokens for social accounts you connect. Tokens are stored encrypted.
- Payment records: plan, amount, status and Razorpay reference numbers. Card, UPI and bank details are handled by Razorpay and are not stored by us.
- Technical and security data: IP address, browser information and activity logs used to keep the service secure.

# How we use information
- To provide, secure and improve the service.
- To process payments and send account or billing messages.
- To respond to your requests and prevent abuse.

# Who we share it with
We share information only with service providers needed to run the service: AI providers that generate content, Meta when you publish to your Pages or Instagram, Razorpay for payments, and hosting and infrastructure providers. We do not sell your personal information.

# Cookies
We use essential cookies for login, security and session management.

# Retention and your rights
We keep information while your account is active and as needed for legal or billing purposes. You may ask us to access, correct or delete your information by writing to {{contact_email}}.

# Changes
We may update this policy and will post the new version on this page.

# Contact
{{business_name}} - {{contact_email}} - {{contact_phone}} - {{address}}
T],
            'refund-policy' => ['Refund and cancellation policy of {{business_name}}.', <<<'T'
# Refund & Cancellation Policy
Our plans give digital access to {{business_name}} for the period shown at purchase. Payments are processed securely by Razorpay.

# Cancellation
You can stop using the service at any time. Your plan stays active until the end of the paid period and does not renew automatically unless we tell you otherwise at purchase.

# Refunds
- If you were charged in error, charged twice, or could not access the service because of a fault on our side, write to {{contact_email}} within 7 days of the payment.
- Approved refunds are returned to the original payment method through Razorpay. Banks and payment providers usually take 5 to 7 business days to show the credit.
- Once a plan period has been substantially used, we may not be able to refund it.

# How to request a refund
Email {{contact_email}} with your account email, the payment date and the Razorpay payment ID.
T],
            'terms-and-conditions' => ['Terms and conditions for using {{business_name}}.', <<<'T'
# Terms & Conditions
By creating an account or using {{site_url}} you agree to these terms.

# Your account
You are responsible for your account, your password and everything done under it. Provide accurate information and keep it up to date.

# Acceptable use
- Do not use the service for unlawful, misleading, hateful or infringing content.
- Only connect social accounts you are authorised to manage, and follow the rules of each platform you publish to.
- Do not attempt to disrupt, probe or misuse the service, including submitting addresses of systems you do not own or are not allowed to access.

# AI-generated content
Content is generated by AI and may be inaccurate. You must review, edit and approve everything before it is published, and you are responsible for the content you approve and publish.

# Third-party services
The service relies on third parties such as AI providers, Meta and Razorpay. We are not responsible for their availability or policies.

# Plans and payments
Prices are shown in Indian Rupees at purchase. Access lasts for the paid period. See our Refund Policy for refunds.

# Intellectual property
You keep ownership of your content. We keep ownership of the service and its software.

# Suspension and termination
We may suspend or end access if these terms are broken or to protect the service or other users.

# Limitation of liability
To the extent permitted by law, the service is provided "as is" and we are not liable for indirect or consequential losses.

# Governing law
These terms are governed by the laws of {{jurisdiction}}.

# Contact
{{business_name}} - {{contact_email}}
T],
            'disclaimer' => ['Disclaimer for the use of {{business_name}}.', <<<'T'
# Disclaimer
- Content created with {{business_name}} is generated by AI and may contain errors. You are responsible for checking it before publishing.
- We do not guarantee any particular reach, engagement, sales or other business result.
- Information provided is for general purposes and is not legal, financial or professional advice.
- The service depends on third-party platforms and providers whose availability and rules can change without notice.
- Links to other websites are provided for convenience; we are not responsible for their content.
T],
        ];
    }
}
