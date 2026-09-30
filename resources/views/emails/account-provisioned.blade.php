<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;color:#1c2b3a">
    <h2 style="color:#193761">Direction générale des Élections</h2>
    <p>Bonjour {{ $user->name }},</p>
    <p>Un compte vous a été créé sur la plateforme RH de la DGE.</p>
    <p><strong>Matricule / identifiant :</strong> {{ $user->matricule ?? $user->email }}<br>
       <strong>Mot de passe provisoire :</strong>
       <span style="font-family:monospace;font-size:16px;background:#eef2f6;padding:2px 6px;border-radius:4px">{{ $password }}</span></p>
    <p>Connectez-vous ici : <a href="{{ $url }}" style="color:#309966">{{ $url }}</a></p>
    <p style="color:#4f5e6e;font-size:14px">À votre première connexion, il vous sera demandé de définir un nouveau mot de passe. Un code de vérification vous sera aussi envoyé par email (double authentification).</p>
</div>
