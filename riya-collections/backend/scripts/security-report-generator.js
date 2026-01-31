#!/usr/bin/env node

/**
 * Comprehensive Security Audit Report Generator
 * for Riya Collections E-commerce Platform
 * 
 * This script generates detailed security audit reports including:
 * - Executive summary for stakeholders
 * - Technical findings for developers
 * - Compliance status for auditors
 * - Remediation roadmap for project managers
 * - Risk assessment for business owners
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
 */

const fs = require('fs');
const path = require('path');
const SecurityAuditor = require('./security-audit');
const PenetrationTester = require('./penetration-testing');

class SecurityReportGenerator {
  constructor(config = {}) {
    this.baseUrl = config.baseUrl || process.env.BASE_URL || 'http://localhost:5000';
    this.outputDir = config.outputDir || path.join(__dirname, '..', 'test-reports');
    this.timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    
    // Ensure output directory exists
    if (!fs.existsSync(this.outputDir)) {
      fs.mkdirSync(this.outputDir, { recursive: true });
    }
  }

  /**
   * Generate comprehensive security audit report
   */
  async generateComprehensiveReport() {
    console.log('📊 Generating Comprehensive Security Audit Report...');
    console.log('=' .repeat(70));
    
    try {
      // Run security audit
      console.log('🔒 Running Security Audit...');
      const auditor = new SecurityAuditor({ baseUrl: this.baseUrl });
      const auditReport = await auditor.runCompleteAudit();
      
      // Run penetration testing
      console.log('\n🎯 Running Penetration Testing...');
      const tester = new PenetrationTester({ baseUrl: this.baseUrl });
      const pentestReport = await tester.runPenetrationTest();
      
      // Generate combined report
      const combinedReport = this.combineReports(auditReport, pentestReport);
      
      // Generate various report formats
      await this.generateExecutiveSummary(combinedReport);
      await this.generateTechnicalReport(combinedReport);
      await this.generateComplianceReport(combinedReport);
      await this.generateRemediationRoadmap(combinedReport);
      await this.generateRiskAssessment(combinedReport);
      await this.generateDashboard(combinedReport);
      
      console.log('\n✅ Comprehensive security audit report generation completed');
      console.log(`📁 Reports saved to: ${this.outputDir}`);
      
      return combinedReport;
      
    } catch (error) {
      console.error('❌ Report generation failed:', error.message);
      throw error;
    }
  }

  /**
   * Combine audit and penetration testing reports
   */
  combineReports(auditReport, pentestReport) {
    const totalVulnerabilities = [
      ...auditReport.vulnerabilities,
      ...pentestReport.exploits,
      ...pentestReport.businessLogicFlaws
    ];

    const severityCounts = {
      critical: totalVulnerabilities.filter(v => v.severity === 'CRITICAL').length,
      high: totalVulnerabilities.filter(v => v.severity === 'HIGH').length,
      medium: totalVulnerabilities.filter(v => v.severity === 'MEDIUM').length,
      low: totalVulnerabilities.filter(v => v.severity === 'LOW').length
    };

    return {
      metadata: {
        timestamp: new Date().toISOString(),
        baseUrl: this.baseUrl,
        auditDuration: auditReport.metadata.duration,
        pentestDuration: pentestReport.metadata.duration,
        totalDuration: parseInt(auditReport.metadata.duration) + parseInt(pentestReport.metadata.duration),
        environment: auditReport.metadata.environment
      },
      summary: {
        totalTests: auditReport.summary.totalTests + pentestReport.summary.totalTests,
        totalVulnerabilities: totalVulnerabilities.length,
        severityCounts,
        auditResults: auditReport.summary,
        pentestResults: pentestReport.summary,
        overallRiskLevel: this.calculateOverallRisk(severityCounts),
        complianceScore: this.calculateComplianceScore(auditReport, pentestReport)
      },
      findings: {
        vulnerabilities: auditReport.vulnerabilities,
        exploits: pentestReport.exploits,
        businessLogicFlaws: pentestReport.businessLogicFlaws,
        testResults: [...auditReport.testResults, ...pentestReport.testResults]
      },
      compliance: {
        owaspTop10: auditReport.owaspTop10Status,
        requirements: auditReport.complianceStatus,
        gaps: this.identifyComplianceGaps(auditReport, pentestReport)
      },
      recommendations: {
        immediate: this.getImmediateRecommendations(totalVulnerabilities),
        shortTerm: this.getShortTermRecommendations(totalVulnerabilities),
        longTerm: this.getLongTermRecommendations(auditReport, pentestReport),
        strategic: this.getStrategicRecommendations(severityCounts)
      },
      riskAssessment: {
        businessImpact: this.assessBusinessImpact(totalVulnerabilities),
        technicalRisk: this.assessTechnicalRisk(totalVulnerabilities),
        complianceRisk: this.assessComplianceRisk(auditReport),
        reputationalRisk: this.assessReputationalRisk(severityCounts),
        financialRisk: this.assessFinancialRisk(totalVulnerabilities)
      }
    };
  }

  /**
   * Generate executive summary report
   */
  async generateExecutiveSummary(report) {
    console.log('📋 Generating Executive Summary...');
    
    const executiveSummary = {
      title: 'Security Audit Executive Summary',
      subtitle: 'Riya Collections E-commerce Platform',
      date: new Date().toLocaleDateString(),
      
      keyFindings: {
        overallRiskLevel: report.summary.overallRiskLevel,
        totalVulnerabilities: report.summary.totalVulnerabilities,
        criticalIssues: report.summary.severityCounts.critical,
        complianceScore: `${report.summary.complianceScore}%`,
        businessImpact: report.riskAssessment.businessImpact.level
      },
      
      executiveOverview: this.generateExecutiveOverview(report),
      riskSummary: this.generateRiskSummary(report),
      businessRecommendations: this.generateBusinessRecommendations(report),
      investmentPriorities: this.generateInvestmentPriorities(report),
      timeline: this.generateRemediationTimeline(report)
    };

    // Generate PDF-ready HTML
    const html = this.generateExecutiveHTML(executiveSummary);
    const filePath = path.join(this.outputDir, `executive-summary-${this.timestamp}.html`);
    fs.writeFileSync(filePath, html);
    
    // Generate JSON for programmatic access
    const jsonPath = path.join(this.outputDir, `executive-summary-${this.timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(executiveSummary, null, 2));
    
    console.log(`✅ Executive summary saved to: ${filePath}`);
  }

  /**
   * Generate technical report for developers
   */
  async generateTechnicalReport(report) {
    console.log('🔧 Generating Technical Report...');
    
    const technicalReport = {
      title: 'Technical Security Assessment Report',
      metadata: report.metadata,
      
      vulnerabilityAnalysis: {
        byCategory: this.categorizeVulnerabilities(report.findings),
        bySeverity: this.groupBySeverity(report.findings),
        byComponent: this.groupByComponent(report.findings),
        trends: this.analyzeTrends(report.findings)
      },
      
      technicalFindings: {
        codeQuality: this.assessCodeQuality(report.findings),
        architecture: this.assessArchitecture(report.findings),
        configuration: this.assessConfiguration(report.findings),
        dependencies: this.assessDependencies(report.findings)
      },
      
      remediationDetails: {
        codeChanges: this.generateCodeChanges(report.findings),
        configurationChanges: this.generateConfigChanges(report.findings),
        architecturalChanges: this.generateArchitecturalChanges(report.findings),
        testingRequirements: this.generateTestingRequirements(report.findings)
      },
      
      implementationGuidance: {
        quickFixes: this.generateQuickFixes(report.findings),
        mediumTermFixes: this.generateMediumTermFixes(report.findings),
        longTermFixes: this.generateLongTermFixes(report.findings),
        preventiveMeasures: this.generatePreventiveMeasures(report.findings)
      }
    };

    const html = this.generateTechnicalHTML(technicalReport);
    const filePath = path.join(this.outputDir, `technical-report-${this.timestamp}.html`);
    fs.writeFileSync(filePath, html);
    
    const jsonPath = path.join(this.outputDir, `technical-report-${this.timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(technicalReport, null, 2));
    
    console.log(`✅ Technical report saved to: ${filePath}`);
  }

  /**
   * Generate compliance report
   */
  async generateComplianceReport(report) {
    console.log('📜 Generating Compliance Report...');
    
    const complianceReport = {
      title: 'Security Compliance Assessment Report',
      standards: {
        owasp: {
          name: 'OWASP Top 10 2021',
          status: report.compliance.owaspTop10,
          score: this.calculateOWASPScore(report.compliance.owaspTop10),
          gaps: this.identifyOWASPGaps(report.compliance.owaspTop10, report.findings)
        },
        requirements: {
          name: 'Project Security Requirements',
          status: report.compliance.requirements,
          score: this.calculateRequirementsScore(report.compliance.requirements),
          gaps: report.compliance.gaps
        },
        industry: {
          pciDss: this.assessPCIDSSCompliance(report.findings),
          gdpr: this.assessGDPRCompliance(report.findings),
          iso27001: this.assessISO27001Compliance(report.findings)
        }
      },
      
      complianceMatrix: this.generateComplianceMatrix(report),
      gapAnalysis: this.generateGapAnalysis(report),
      remediationPlan: this.generateComplianceRemediationPlan(report),
      certificationReadiness: this.assessCertificationReadiness(report)
    };

    const html = this.generateComplianceHTML(complianceReport);
    const filePath = path.join(this.outputDir, `compliance-report-${this.timestamp}.html`);
    fs.writeFileSync(filePath, html);
    
    const jsonPath = path.join(this.outputDir, `compliance-report-${this.timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(complianceReport, null, 2));
    
    console.log(`✅ Compliance report saved to: ${filePath}`);
  }

  /**
   * Generate remediation roadmap
   */
  async generateRemediationRoadmap(report) {
    console.log('🗺️ Generating Remediation Roadmap...');
    
    const roadmap = {
      title: 'Security Remediation Roadmap',
      
      phases: {
        immediate: {
          name: 'Immediate Actions (0-2 weeks)',
          priority: 'CRITICAL',
          items: this.getImmediateActions(report.findings),
          effort: this.estimateEffort(this.getImmediateActions(report.findings)),
          resources: this.estimateResources(this.getImmediateActions(report.findings))
        },
        
        shortTerm: {
          name: 'Short Term (2-8 weeks)',
          priority: 'HIGH',
          items: this.getShortTermActions(report.findings),
          effort: this.estimateEffort(this.getShortTermActions(report.findings)),
          resources: this.estimateResources(this.getShortTermActions(report.findings))
        },
        
        mediumTerm: {
          name: 'Medium Term (2-6 months)',
          priority: 'MEDIUM',
          items: this.getMediumTermActions(report.findings),
          effort: this.estimateEffort(this.getMediumTermActions(report.findings)),
          resources: this.estimateResources(this.getMediumTermActions(report.findings))
        },
        
        longTerm: {
          name: 'Long Term (6+ months)',
          priority: 'LOW',
          items: this.getLongTermActions(report.findings),
          effort: this.estimateEffort(this.getLongTermActions(report.findings)),
          resources: this.estimateResources(this.getLongTermActions(report.findings))
        }
      },
      
      milestones: this.generateMilestones(report),
      dependencies: this.identifyDependencies(report),
      riskReduction: this.calculateRiskReduction(report),
      costBenefit: this.calculateCostBenefit(report)
    };

    const html = this.generateRoadmapHTML(roadmap);
    const filePath = path.join(this.outputDir, `remediation-roadmap-${this.timestamp}.html`);
    fs.writeFileSync(filePath, html);
    
    const jsonPath = path.join(this.outputDir, `remediation-roadmap-${this.timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(roadmap, null, 2));
    
    console.log(`✅ Remediation roadmap saved to: ${filePath}`);
  }

  /**
   * Generate risk assessment report
   */
  async generateRiskAssessment(report) {
    console.log('⚠️ Generating Risk Assessment...');
    
    const riskAssessment = {
      title: 'Comprehensive Risk Assessment',
      
      riskProfile: {
        overall: report.summary.overallRiskLevel,
        business: report.riskAssessment.businessImpact.level,
        technical: report.riskAssessment.technicalRisk.level,
        compliance: report.riskAssessment.complianceRisk.level,
        reputational: report.riskAssessment.reputationalRisk.level,
        financial: report.riskAssessment.financialRisk.level
      },
      
      riskMatrix: this.generateRiskMatrix(report),
      threatLandscape: this.analyzeThreatLandscape(report),
      vulnerabilityTrends: this.analyzeVulnerabilityTrends(report),
      attackVectors: this.identifyAttackVectors(report),
      
      businessImpactAnalysis: {
        revenue: this.assessRevenueImpact(report),
        operations: this.assessOperationalImpact(report),
        reputation: this.assessReputationalImpact(report),
        legal: this.assessLegalImpact(report),
        customer: this.assessCustomerImpact(report)
      },
      
      riskMitigation: {
        preventive: this.generatePreventiveControls(report),
        detective: this.generateDetectiveControls(report),
        corrective: this.generateCorrectiveControls(report),
        compensating: this.generateCompensatingControls(report)
      }
    };

    const html = this.generateRiskAssessmentHTML(riskAssessment);
    const filePath = path.join(this.outputDir, `risk-assessment-${this.timestamp}.html`);
    fs.writeFileSync(filePath, html);
    
    const jsonPath = path.join(this.outputDir, `risk-assessment-${this.timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(riskAssessment, null, 2));
    
    console.log(`✅ Risk assessment saved to: ${filePath}`);
  }

  /**
   * Generate interactive dashboard
   */
  async generateDashboard(report) {
    console.log('📊 Generating Interactive Dashboard...');
    
    const dashboard = {
      title: 'Security Dashboard',
      
      metrics: {
        vulnerabilities: {
          total: report.summary.totalVulnerabilities,
          critical: report.summary.severityCounts.critical,
          high: report.summary.severityCounts.high,
          medium: report.summary.severityCounts.medium,
          low: report.summary.severityCounts.low
        },
        
        compliance: {
          overall: report.summary.complianceScore,
          owasp: this.calculateOWASPScore(report.compliance.owaspTop10),
          requirements: this.calculateRequirementsScore(report.compliance.requirements)
        },
        
        risk: {
          level: report.summary.overallRiskLevel,
          score: this.calculateRiskScore(report),
          trend: 'stable' // Would be calculated from historical data
        }
      },
      
      charts: {
        vulnerabilityDistribution: this.generateVulnerabilityChart(report),
        complianceStatus: this.generateComplianceChart(report),
        riskTrends: this.generateRiskTrendChart(report),
        remediationProgress: this.generateRemediationChart(report)
      },
      
      alerts: this.generateSecurityAlerts(report),
      recommendations: this.generateDashboardRecommendations(report)
    };

    const html = this.generateDashboardHTML(dashboard);
    const filePath = path.join(this.outputDir, `security-dashboard-${this.timestamp}.html`);
    fs.writeFileSync(filePath, html);
    
    console.log(`✅ Security dashboard saved to: ${filePath}`);
  }

  // Helper methods for calculations and analysis
  calculateOverallRisk(severityCounts) {
    if (severityCounts.critical > 0) return 'CRITICAL';
    if (severityCounts.high > 2) return 'HIGH';
    if (severityCounts.high > 0 || severityCounts.medium > 5) return 'MEDIUM';
    return 'LOW';
  }

  calculateComplianceScore(auditReport, pentestReport) {
    const requirements = auditReport.complianceStatus;
    const total = Object.keys(requirements).length;
    const compliant = Object.values(requirements).filter(status => status === 'COMPLIANT').length;
    return Math.round((compliant / total) * 100);
  }

  identifyComplianceGaps(auditReport, pentestReport) {
    const gaps = [];
    
    Object.entries(auditReport.complianceStatus).forEach(([req, status]) => {
      if (status === 'NON_COMPLIANT') {
        gaps.push({
          requirement: req,
          status: status,
          severity: this.getRequirementSeverity(req),
          remediation: this.getRequirementRemediation(req)
        });
      }
    });
    
    return gaps;
  }

  getImmediateRecommendations(vulnerabilities) {
    const critical = vulnerabilities.filter(v => v.severity === 'CRITICAL');
    return critical.map(v => ({
      action: `Fix ${v.type}`,
      description: v.description,
      priority: 'IMMEDIATE',
      effort: 'HIGH'
    }));
  }

  getShortTermRecommendations(vulnerabilities) {
    const high = vulnerabilities.filter(v => v.severity === 'HIGH');
    return high.map(v => ({
      action: `Address ${v.type}`,
      description: v.description,
      priority: 'HIGH',
      effort: 'MEDIUM'
    }));
  }

  getLongTermRecommendations(auditReport, pentestReport) {
    return [
      {
        action: 'Implement Security Development Lifecycle (SDL)',
        description: 'Integrate security into the development process',
        priority: 'MEDIUM',
        effort: 'HIGH'
      },
      {
        action: 'Regular Security Training',
        description: 'Provide ongoing security training for development team',
        priority: 'MEDIUM',
        effort: 'MEDIUM'
      },
      {
        action: 'Automated Security Testing',
        description: 'Implement automated security testing in CI/CD pipeline',
        priority: 'MEDIUM',
        effort: 'HIGH'
      }
    ];
  }

  getStrategicRecommendations(severityCounts) {
    const recommendations = [];
    
    if (severityCounts.critical > 0 || severityCounts.high > 3) {
      recommendations.push({
        action: 'Establish Security Team',
        description: 'Create dedicated security team or hire security expert',
        priority: 'HIGH',
        effort: 'HIGH'
      });
    }
    
    recommendations.push({
      action: 'Security Governance Framework',
      description: 'Implement comprehensive security governance and policies',
      priority: 'MEDIUM',
      effort: 'HIGH'
    });
    
    return recommendations;
  }

  assessBusinessImpact(vulnerabilities) {
    const criticalCount = vulnerabilities.filter(v => v.severity === 'CRITICAL').length;
    const highCount = vulnerabilities.filter(v => v.severity === 'HIGH').length;
    
    let level = 'LOW';
    let description = 'Minimal business impact expected';
    
    if (criticalCount > 0) {
      level = 'CRITICAL';
      description = 'Severe business impact - potential for significant financial loss, data breach, and reputational damage';
    } else if (highCount > 2) {
      level = 'HIGH';
      description = 'High business impact - potential for financial loss and customer trust issues';
    } else if (highCount > 0) {
      level = 'MEDIUM';
      description = 'Moderate business impact - some risk to operations and customer data';
    }
    
    return { level, description, vulnerabilities: criticalCount + highCount };
  }

  assessTechnicalRisk(vulnerabilities) {
    const injectionVulns = vulnerabilities.filter(v => 
      v.type.includes('INJECTION') || v.type.includes('SQL') || v.type.includes('XSS')
    ).length;
    
    const authVulns = vulnerabilities.filter(v => 
      v.type.includes('AUTH') || v.type.includes('PRIVILEGE')
    ).length;
    
    let level = 'LOW';
    if (injectionVulns > 0 || authVulns > 1) level = 'HIGH';
    else if (authVulns > 0) level = 'MEDIUM';
    
    return {
      level,
      injectionRisk: injectionVulns,
      authenticationRisk: authVulns,
      systemCompromiseRisk: injectionVulns > 0 ? 'HIGH' : 'LOW'
    };
  }

  assessComplianceRisk(auditReport) {
    const nonCompliant = Object.values(auditReport.complianceStatus)
      .filter(status => status === 'NON_COMPLIANT').length;
    
    const total = Object.keys(auditReport.complianceStatus).length;
    const compliancePercentage = ((total - nonCompliant) / total) * 100;
    
    let level = 'LOW';
    if (compliancePercentage < 70) level = 'HIGH';
    else if (compliancePercentage < 85) level = 'MEDIUM';
    
    return {
      level,
      compliancePercentage,
      nonCompliantRequirements: nonCompliant,
      auditRisk: level === 'HIGH' ? 'SIGNIFICANT' : 'MANAGEABLE'
    };
  }

  assessReputationalRisk(severityCounts) {
    const totalCriticalHigh = severityCounts.critical + severityCounts.high;
    
    let level = 'LOW';
    let description = 'Minimal reputational risk';
    
    if (totalCriticalHigh > 3) {
      level = 'HIGH';
      description = 'High reputational risk - security issues could damage brand trust';
    } else if (totalCriticalHigh > 0) {
      level = 'MEDIUM';
      description = 'Moderate reputational risk - some potential for negative publicity';
    }
    
    return { level, description, riskFactors: totalCriticalHigh };
  }

  assessFinancialRisk(vulnerabilities) {
    const paymentVulns = vulnerabilities.filter(v => 
      v.type.includes('PAYMENT') || v.description.toLowerCase().includes('payment')
    ).length;
    
    const dataVulns = vulnerabilities.filter(v => 
      v.type.includes('INJECTION') || v.type.includes('ACCESS')
    ).length;
    
    let estimatedLoss = 0;
    if (paymentVulns > 0) estimatedLoss += 50000; // Payment fraud risk
    if (dataVulns > 0) estimatedLoss += 100000; // Data breach costs
    
    return {
      level: estimatedLoss > 75000 ? 'HIGH' : estimatedLoss > 25000 ? 'MEDIUM' : 'LOW',
      estimatedLoss,
      paymentRisk: paymentVulns > 0,
      dataBreachRisk: dataVulns > 0
    };
  }

  // HTML generation methods
  generateExecutiveHTML(summary) {
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${summary.title}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #007bff; padding-bottom: 20px; }
        .header h1 { color: #333; margin: 0; font-size: 2.5em; }
        .header h2 { color: #666; margin: 10px 0; font-weight: normal; }
        .key-findings { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
        .finding-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .finding-card h3 { margin: 0 0 10px 0; font-size: 1.1em; }
        .finding-card .value { font-size: 2em; font-weight: bold; margin: 10px 0; }
        .risk-critical { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }
        .risk-high { background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%); }
        .risk-medium { background: linear-gradient(135deg, #ffee58 0%, #fbc02d 100%); color: #333; }
        .risk-low { background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%); }
        .section { margin: 40px 0; }
        .section h2 { color: #333; border-left: 4px solid #007bff; padding-left: 15px; }
        .overview { background: #f8f9fa; padding: 25px; border-radius: 8px; border-left: 4px solid #007bff; }
        .recommendations { background: #fff3cd; padding: 25px; border-radius: 8px; border-left: 4px solid #ffc107; }
        .timeline { background: #d4edda; padding: 25px; border-radius: 8px; border-left: 4px solid #28a745; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
        .priority-immediate { color: #dc3545; font-weight: bold; }
        .priority-high { color: #fd7e14; font-weight: bold; }
        .priority-medium { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>${summary.title}</h1>
            <h2>${summary.subtitle}</h2>
            <p>Report Date: ${summary.date}</p>
        </div>

        <div class="key-findings">
            <div class="finding-card risk-${summary.keyFindings.overallRiskLevel.toLowerCase()}">
                <h3>Overall Risk Level</h3>
                <div class="value">${summary.keyFindings.overallRiskLevel}</div>
            </div>
            <div class="finding-card">
                <h3>Total Vulnerabilities</h3>
                <div class="value">${summary.keyFindings.totalVulnerabilities}</div>
            </div>
            <div class="finding-card risk-critical">
                <h3>Critical Issues</h3>
                <div class="value">${summary.keyFindings.criticalIssues}</div>
            </div>
            <div class="finding-card">
                <h3>Compliance Score</h3>
                <div class="value">${summary.keyFindings.complianceScore}</div>
            </div>
        </div>

        <div class="section">
            <h2>Executive Overview</h2>
            <div class="overview">
                <p>${summary.executiveOverview}</p>
            </div>
        </div>

        <div class="section">
            <h2>Risk Summary</h2>
            <div class="overview">
                <p>${summary.riskSummary}</p>
            </div>
        </div>

        <div class="section">
            <h2>Business Recommendations</h2>
            <div class="recommendations">
                <ul>
                    ${summary.businessRecommendations.map(rec => `<li class="priority-${rec.priority.toLowerCase()}">${rec.action}: ${rec.description}</li>`).join('')}
                </ul>
            </div>
        </div>

        <div class="section">
            <h2>Investment Priorities</h2>
            <div class="timeline">
                <ul>
                    ${summary.investmentPriorities.map(priority => `<li><strong>${priority.area}</strong>: ${priority.description} (${priority.investment})</li>`).join('')}
                </ul>
            </div>
        </div>

        <div class="section">
            <h2>Remediation Timeline</h2>
            <div class="timeline">
                <ul>
                    ${summary.timeline.map(milestone => `<li><strong>${milestone.phase}</strong>: ${milestone.description} (${milestone.timeframe})</li>`).join('')}
                </ul>
            </div>
        </div>
    </div>
</body>
</html>`;
  }

  generateTechnicalHTML(report) {
    // Similar structure but focused on technical details
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${report.title}</title>
    <style>
        body { font-family: 'Courier New', monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        .container { max-width: 1200px; margin: 0 auto; background: #2d2d30; padding: 20px; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 30px; color: #569cd6; }
        .vulnerability { margin: 15px 0; padding: 15px; background: #3c3c3c; border-left: 4px solid #f44747; border-radius: 4px; }
        .vulnerability.high { border-left-color: #ff8c00; }
        .vulnerability.medium { border-left-color: #ffcc02; }
        .vulnerability.low { border-left-color: #007acc; }
        .code { background: #1e1e1e; padding: 10px; border-radius: 4px; font-family: 'Courier New', monospace; overflow-x: auto; }
        .section { margin: 30px 0; }
        .section h2 { color: #569cd6; border-bottom: 2px solid #569cd6; padding-bottom: 10px; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>${report.title}</h1>
            <p>Generated: ${report.metadata.timestamp}</p>
        </div>

        <div class="section">
            <h2>Vulnerability Analysis</h2>
            ${Object.entries(report.vulnerabilityAnalysis.byCategory).map(([category, vulns]) => `
                <h3>${category}</h3>
                ${vulns.map(vuln => `
                    <div class="vulnerability ${vuln.severity.toLowerCase()}">
                        <h4>${vuln.type} (${vuln.severity})</h4>
                        <p>${vuln.description}</p>
                        ${vuln.details ? `<pre>${JSON.stringify(vuln.details, null, 2)}</pre>` : ''}
                    </div>
                `).join('')}
            `).join('')}
        </div>

        <div class="section">
            <h2>Remediation Details</h2>
            ${report.remediationDetails.codeChanges.map(change => `
                <div class="code">
                    <h4>${change.file}</h4>
                    <p>${change.description}</p>
                    <pre>${change.code}</pre>
                </div>
            `).join('')}
        </div>
    </div>
</body>
</html>`;
  }

  generateComplianceHTML(report) {
    // Compliance-focused HTML template
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${report.title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .compliance-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .compliance-table th, .compliance-table td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        .compliance-table th { background-color: #f8f9fa; }
        .compliant { color: #28a745; font-weight: bold; }
        .non-compliant { color: #dc3545; font-weight: bold; }
        .partial { color: #ffc107; font-weight: bold; }
        .gap { background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>${report.title}</h1>
        
        <h2>OWASP Top 10 Compliance</h2>
        <table class="compliance-table">
            <thead>
                <tr><th>Category</th><th>Status</th><th>Score</th></tr>
            </thead>
            <tbody>
                ${Object.entries(report.standards.owasp.status).map(([category, status]) => `
                    <tr>
                        <td>${category}</td>
                        <td class="${status.toLowerCase().replace('_', '-')}">${status}</td>
                        <td>${this.getStatusScore(status)}%</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>

        <h2>Gap Analysis</h2>
        ${report.gapAnalysis.map(gap => `
            <div class="gap">
                <h4>${gap.requirement}</h4>
                <p><strong>Status:</strong> ${gap.status}</p>
                <p><strong>Impact:</strong> ${gap.impact}</p>
                <p><strong>Remediation:</strong> ${gap.remediation}</p>
            </div>
        `).join('')}
    </div>
</body>
</html>`;
  }

  generateRoadmapHTML(roadmap) {
    // Roadmap visualization HTML
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${roadmap.title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .phase { background: white; margin: 20px 0; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .phase-header { display: flex; justify-content: between; align-items: center; margin-bottom: 20px; }
        .phase-title { font-size: 1.5em; font-weight: bold; }
        .priority-critical { border-left: 5px solid #dc3545; }
        .priority-high { border-left: 5px solid #fd7e14; }
        .priority-medium { border-left: 5px solid #ffc107; }
        .priority-low { border-left: 5px solid #28a745; }
        .item { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .effort-estimate { float: right; background: #007bff; color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>${roadmap.title}</h1>
        
        ${Object.entries(roadmap.phases).map(([key, phase]) => `
            <div class="phase priority-${phase.priority.toLowerCase()}">
                <div class="phase-header">
                    <div class="phase-title">${phase.name}</div>
                    <div class="effort-estimate">${phase.effort} effort</div>
                </div>
                
                ${phase.items.map(item => `
                    <div class="item">
                        <h4>${item.action}</h4>
                        <p>${item.description}</p>
                        <small>Priority: ${item.priority} | Effort: ${item.effort}</small>
                    </div>
                `).join('')}
            </div>
        `).join('')}
    </div>
</body>
</html>`;
  }

  generateRiskAssessmentHTML(assessment) {
    // Risk assessment visualization
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${assessment.title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .risk-matrix { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 20px 0; }
        .risk-cell { padding: 20px; text-align: center; border-radius: 4px; font-weight: bold; }
        .risk-critical { background: #dc3545; color: white; }
        .risk-high { background: #fd7e14; color: white; }
        .risk-medium { background: #ffc107; color: black; }
        .risk-low { background: #28a745; color: white; }
        .impact-analysis { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
        .impact-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>${assessment.title}</h1>
        
        <h2>Risk Profile</h2>
        <div class="risk-matrix">
            ${Object.entries(assessment.riskProfile).map(([type, level]) => `
                <div class="risk-cell risk-${level.toLowerCase()}">
                    <div>${type.toUpperCase()}</div>
                    <div>${level}</div>
                </div>
            `).join('')}
        </div>

        <h2>Business Impact Analysis</h2>
        <div class="impact-analysis">
            ${Object.entries(assessment.businessImpactAnalysis).map(([area, impact]) => `
                <div class="impact-card">
                    <h4>${area.charAt(0).toUpperCase() + area.slice(1)} Impact</h4>
                    <p><strong>Level:</strong> ${impact.level}</p>
                    <p>${impact.description}</p>
                </div>
            `).join('')}
        </div>
    </div>
</body>
</html>`;
  }

  generateDashboardHTML(dashboard) {
    // Interactive dashboard with charts
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${dashboard.title}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1400px; margin: 0 auto; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .metric-value { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
        .charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin: 30px 0; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .alerts { background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0; }
        .alert { margin: 10px 0; padding: 10px; background: white; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>${dashboard.title}</h1>
        
        <div class="metrics">
            <div class="metric-card">
                <h3>Total Vulnerabilities</h3>
                <div class="metric-value">${dashboard.metrics.vulnerabilities.total}</div>
            </div>
            <div class="metric-card">
                <h3>Critical Issues</h3>
                <div class="metric-value" style="color: #dc3545;">${dashboard.metrics.vulnerabilities.critical}</div>
            </div>
            <div class="metric-card">
                <h3>Compliance Score</h3>
                <div class="metric-value" style="color: #28a745;">${dashboard.metrics.compliance.overall}%</div>
            </div>
            <div class="metric-card">
                <h3>Risk Level</h3>
                <div class="metric-value" style="color: ${this.getRiskColor(dashboard.metrics.risk.level)};">${dashboard.metrics.risk.level}</div>
            </div>
        </div>

        <div class="charts">
            <div class="chart-container">
                <h3>Vulnerability Distribution</h3>
                <canvas id="vulnerabilityChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Compliance Status</h3>
                <canvas id="complianceChart"></canvas>
            </div>
        </div>

        <div class="alerts">
            <h3>Security Alerts</h3>
            ${dashboard.alerts.map(alert => `
                <div class="alert">
                    <strong>${alert.severity}:</strong> ${alert.message}
                </div>
            `).join('')}
        </div>
    </div>

    <script>
        // Vulnerability Distribution Chart
        const vulnCtx = document.getElementById('vulnerabilityChart').getContext('2d');
        new Chart(vulnCtx, {
            type: 'doughnut',
            data: {
                labels: ['Critical', 'High', 'Medium', 'Low'],
                datasets: [{
                    data: [
                        ${dashboard.metrics.vulnerabilities.critical},
                        ${dashboard.metrics.vulnerabilities.high},
                        ${dashboard.metrics.vulnerabilities.medium},
                        ${dashboard.metrics.vulnerabilities.low}
                    ],
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#28a745']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Compliance Chart
        const compCtx = document.getElementById('complianceChart').getContext('2d');
        new Chart(compCtx, {
            type: 'bar',
            data: {
                labels: ['Overall', 'OWASP', 'Requirements'],
                datasets: [{
                    label: 'Compliance %',
                    data: [
                        ${dashboard.metrics.compliance.overall},
                        ${dashboard.metrics.compliance.owasp},
                        ${dashboard.metrics.compliance.requirements}
                    ],
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    </script>
</body>
</html>`;
  }

  // Additional helper methods would be implemented here...
  generateExecutiveOverview(report) {
    const riskLevel = report.summary.overallRiskLevel;
    const vulnCount = report.summary.totalVulnerabilities;
    const criticalCount = report.summary.severityCounts.critical;
    
    if (criticalCount > 0) {
      return `The security assessment of Riya Collections e-commerce platform has identified ${vulnCount} security vulnerabilities, including ${criticalCount} critical issues that require immediate attention. The overall risk level is ${riskLevel}, indicating significant security concerns that could impact business operations, customer data protection, and regulatory compliance. Immediate action is required to address critical vulnerabilities and implement a comprehensive security improvement program.`;
    } else if (riskLevel === 'HIGH') {
      return `The security assessment reveals ${vulnCount} vulnerabilities with a ${riskLevel} overall risk level. While no critical issues were found, several high-priority security concerns require prompt attention to prevent potential security incidents and ensure robust protection of customer data and business operations.`;
    } else {
      return `The security assessment shows ${vulnCount} vulnerabilities with a ${riskLevel} overall risk level. The platform demonstrates good security practices with room for improvement in specific areas. A structured approach to addressing identified issues will further strengthen the security posture.`;
    }
  }

  generateRiskSummary(report) {
    return `Key risk factors include potential for ${report.riskAssessment.businessImpact.description.toLowerCase()}, ${report.riskAssessment.technicalRisk.systemCompromiseRisk.toLowerCase()} system compromise risk, and ${report.riskAssessment.complianceRisk.auditRisk.toLowerCase()} audit risk. Financial impact is estimated at ${report.riskAssessment.financialRisk.level.toLowerCase()} level with potential losses up to $${report.riskAssessment.financialRisk.estimatedLoss.toLocaleString()}.`;
  }

  generateBusinessRecommendations(report) {
    const recommendations = [];
    
    if (report.summary.severityCounts.critical > 0) {
      recommendations.push({
        priority: 'IMMEDIATE',
        action: 'Emergency Security Response',
        description: 'Activate incident response team and address critical vulnerabilities within 48 hours'
      });
    }
    
    if (report.summary.overallRiskLevel === 'HIGH' || report.summary.overallRiskLevel === 'CRITICAL') {
      recommendations.push({
        priority: 'HIGH',
        action: 'Security Investment',
        description: 'Allocate budget for security tools, training, and expert consultation'
      });
    }
    
    recommendations.push({
      priority: 'MEDIUM',
      action: 'Security Governance',
      description: 'Establish security policies, procedures, and regular assessment schedule'
    });
    
    return recommendations;
  }

  generateInvestmentPriorities(report) {
    const priorities = [];
    
    if (report.summary.severityCounts.critical > 0) {
      priorities.push({
        area: 'Critical Vulnerability Remediation',
        description: 'Immediate fixes for critical security issues',
        investment: 'High Priority',
        timeframe: '1-2 weeks'
      });
    }
    
    priorities.push({
      area: 'Security Tools and Infrastructure',
      description: 'Implement automated security testing and monitoring',
      investment: 'Medium Priority',
      timeframe: '1-3 months'
    });
    
    priorities.push({
      area: 'Team Training and Development',
      description: 'Security training for development and operations teams',
      investment: 'Ongoing',
      timeframe: 'Continuous'
    });
    
    return priorities;
  }

  generateRemediationTimeline(report) {
    return [
      {
        phase: 'Immediate (0-2 weeks)',
        description: 'Address critical vulnerabilities and implement emergency fixes',
        timeframe: '2 weeks'
      },
      {
        phase: 'Short Term (2-8 weeks)',
        description: 'Fix high-priority issues and implement security controls',
        timeframe: '6 weeks'
      },
      {
        phase: 'Medium Term (2-6 months)',
        description: 'Address remaining vulnerabilities and enhance security processes',
        timeframe: '4 months'
      },
      {
        phase: 'Long Term (6+ months)',
        description: 'Implement advanced security measures and continuous improvement',
        timeframe: 'Ongoing'
      }
    ];
  }

  // Additional helper methods for various calculations and data processing...
  categorizeVulnerabilities(findings) {
    const categories = {
      'Input Validation': [],
      'Authentication': [],
      'Authorization': [],
      'Cryptography': [],
      'Configuration': [],
      'Business Logic': []
    };
    
    const allVulns = [...findings.vulnerabilities, ...findings.exploits, ...findings.businessLogicFlaws];
    
    allVulns.forEach(vuln => {
      if (vuln.type.includes('INPUT') || vuln.type.includes('VALIDATION') || vuln.type.includes('XSS') || vuln.type.includes('INJECTION')) {
        categories['Input Validation'].push(vuln);
      } else if (vuln.type.includes('AUTH') && !vuln.type.includes('AUTHORIZATION')) {
        categories['Authentication'].push(vuln);
      } else if (vuln.type.includes('AUTHORIZATION') || vuln.type.includes('PRIVILEGE')) {
        categories['Authorization'].push(vuln);
      } else if (vuln.type.includes('CRYPTO') || vuln.type.includes('ENCRYPTION')) {
        categories['Cryptography'].push(vuln);
      } else if (vuln.type.includes('CONFIG') || vuln.type.includes('HEADER')) {
        categories['Configuration'].push(vuln);
      } else {
        categories['Business Logic'].push(vuln);
      }
    });
    
    return categories;
  }

  groupBySeverity(findings) {
    const allVulns = [...findings.vulnerabilities, ...findings.exploits, ...findings.businessLogicFlaws];
    return {
      'CRITICAL': allVulns.filter(v => v.severity === 'CRITICAL'),
      'HIGH': allVulns.filter(v => v.severity === 'HIGH'),
      'MEDIUM': allVulns.filter(v => v.severity === 'MEDIUM'),
      'LOW': allVulns.filter(v => v.severity === 'LOW')
    };
  }

  groupByComponent(findings) {
    // Group vulnerabilities by system component
    return {
      'Authentication System': [],
      'Payment Processing': [],
      'Product Management': [],
      'Order Management': [],
      'User Management': [],
      'File Upload': [],
      'API Endpoints': []
    };
  }

  analyzeTrends(findings) {
    // Analyze vulnerability trends (would use historical data in real implementation)
    return {
      increasing: ['Input Validation', 'Authentication'],
      decreasing: ['Configuration'],
      stable: ['Authorization', 'Cryptography']
    };
  }

  getRiskColor(level) {
    const colors = {
      'CRITICAL': '#dc3545',
      'HIGH': '#fd7e14',
      'MEDIUM': '#ffc107',
      'LOW': '#28a745'
    };
    return colors[level] || '#6c757d';
  }

  getStatusScore(status) {
    const scores = {
      'COMPLIANT': 100,
      'TESTED': 85,
      'PARTIAL': 50,
      'NON_COMPLIANT': 0,
      'MANUAL_CHECK_REQUIRED': 25
    };
    return scores[status] || 0;
  }

  // More helper methods would be implemented here for complete functionality...
}

// Export for use as module
module.exports = SecurityReportGenerator;

// Run if called directly
if (require.main === module) {
  const generator = new SecurityReportGenerator();
  generator.generateComprehensiveReport()
    .then(() => {
      console.log('\n✅ Security report generation completed successfully');
      process.exit(0);
    })
    .catch((error) => {
      console.error('\n❌ Security report generation failed:', error);
      process.exit(1);
    });
}